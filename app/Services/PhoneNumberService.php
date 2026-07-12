<?php

namespace App\Services;

use App\Exceptions\InvalidPhoneNumberException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberService
{
    /**
     * Default region so staff can type a local-format number (e.g. "7771234")
     * without prefixing the country code themselves.
     */
    private const DEFAULT_REGION = 'GM';

    /**
     * Canonicalizes a raw, staff-entered phone number to E.164 (e.g.
     * "+2207771234"), so two different-looking inputs for the same number
     * ("7771234" vs "+220 777 1234") always resolve to the identical stored
     * value before the members.phone_active uniqueness check ever runs.
     *
     * @throws InvalidPhoneNumberException if the number can't be parsed or
     *                                      isn't a real, valid number.
     */
    public function canonicalize(string $rawNumber): string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($rawNumber, self::DEFAULT_REGION);
        } catch (NumberParseException $e) {
            throw new InvalidPhoneNumberException(
                "\"{$rawNumber}\" could not be parsed as a phone number.",
                previous: $e,
            );
        }

        if (! $util->isValidNumber($parsed)) {
            throw new InvalidPhoneNumberException("\"{$rawNumber}\" is not a valid phone number.");
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }
}
