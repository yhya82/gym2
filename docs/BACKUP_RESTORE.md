# Backup & Restore

This app is the system of record for membership and payment data — `payments`
in particular has no delete path anywhere in the app (Rule 3: payment history
is immutable) and is the only record of money collected. Losing the database
with no backup is the single highest-consequence failure mode this project
has, independent of anything in the code itself.

## What gets backed up

1. **The database** (`gym2_db`) — every table, including the
   `trg_payments_before_insert` trigger and the generated columns
   (`*_active` uniqueness columns). `mysqldump --routines --triggers --events`
   is used specifically so the trigger comes back on restore, not just the
   rows.
2. **`storage/app/public`** — currently just the Settings > Logo upload
   (`app/Livewire/SettingsPage.php`). Not in git, not in the database, so a
   DB-only backup would silently lose it. Everything else the app writes to
   disk (`storage/logs`, `storage/framework/*`) is disposable and
   deliberately not included.

Not covered here: the application code itself (it's in git) and `.env`
(secrets — back this up separately, out of band, if at all).

## Automated backups

`deploy/backup/backup-database.sh` does the dump + files archive + retention
pruning in one run. It reads `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/
`DB_PASSWORD` straight out of the app's own `.env`, so it can never drift out
of sync with what the app is actually configured to connect to.

Install the nightly cron job:

```bash
crontab -l | { cat; cat deploy/cron/gym2-backup; } | crontab -
```

This runs at 00:30 daily (after the 00:05 expiry job — see
`deploy/cron/gym2-schedule` — so a backup always reflects that day's expiry
transitions), writes to `/var/backups/gym2/database/`, and prunes anything
older than 14 days. Adjust the destination/retention by editing the cron line
directly — both are plain arguments to the script:

```bash
./deploy/backup/backup-database.sh <backup-dir> <retention-days>
```

`/var/backups/gym2/database` should itself be on a different physical
disk/volume than the database's own data directory — a backup that lives on
the same disk as what it's backing up doesn't protect against a disk
failure, only against a bad `DELETE`.

## Restoring

**This is destructive** — it replaces every table in the target database.

```bash
./deploy/backup/restore-database.sh /var/backups/gym2/database/gym2_db-20260713-071613.sql.gz
```

Prompts for confirmation unless run with `--force`. Restores the files
archive the same way, straight to the destination:

```bash
tar -xzf /var/backups/gym2/database/storage-public-20260713-071613.tar.gz -C storage/app/public
```

After restoring, confirm the trigger came back before trusting the
database with real traffic again:

```sql
SHOW TRIGGERS;
```

If it's missing, the dump was taken without `--triggers` (shouldn't happen
via the provided script, but would happen with an ad-hoc `mysqldump` run
that doesn't include it) — overpayment would silently stop being enforced
at the database layer.

## Restore has been tested

The restore path was verified end-to-end while writing this doc: a real
dump of the dev database was restored into a separate scratch database, and
row counts across `members`/`users`/`plans`/`subscriptions`/`payments`/
`audit_logs` matched the source exactly, with `trg_payments_before_insert`
intact in `SHOW TRIGGERS`. That proves the mechanism works — it doesn't
substitute for testing it again periodically against whatever the schema
looks like at the time.

**Re-test the restore path at least quarterly**, and after any migration
that changes triggers, generated columns, or `CHECK` constraints — restore
the latest backup into a scratch database (never into `gym2_db` itself) and
re-run the row-count + `SHOW TRIGGERS` check above.

## Recovery time expectations

For the data volumes this app runs at (a single gym's members/plans/
payments), a `mysqldump`-based restore completes in seconds to low minutes.
This approach stops being adequate once the database reaches a size where a
full logical restore takes longer than the business can tolerate being
down — at that point, look at MariaDB's physical backup tooling
(`mariabackup`) instead of revisiting this document's assumptions.
