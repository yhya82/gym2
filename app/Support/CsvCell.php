<?php

namespace App\Support;

/**
 * Makes a value safe to put in a CSV cell that someone will open in Excel,
 * Google Sheets or LibreOffice.
 *
 * Those programs treat a cell starting with = + - @ (or a tab / carriage
 * return before one) as a formula and run it — quoted in the CSV or not.
 * Member names, plan names and log descriptions are typed by people, so a
 * name like =HYPERLINK("http://…") would otherwise become a live link or
 * worse in the Admin's spreadsheet. A leading apostrophe makes the program
 * show the text as-is.
 *
 * Used by every CSV export (members, audit log).
 */
final class CsvCell
{
    public static function text(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
