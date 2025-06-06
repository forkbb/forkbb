<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use DateTime;
use DateTimeZone;
use IntlDateFormatter;
use IntlException;
use function \ForkBB\__;

trait TimeZoneTrait
{
    protected function createTimeZoneOptions() : array
    {
        $list = [];

        foreach (DateTimeZone::listIdentifiers() as $zone) {
            $dateTimeZone          = new DateTimeZone($zone);
            $dateTime              = new DateTime('now', $dateTimeZone);
            $offset                = $dateTime->getOffset();
            $list[$offset][$zone]  = __($zone);
        }

        \ksort($list, \SORT_NUMERIC);

        $options = [];
        $now     = \time();

        foreach ($list as $offset => $group) {
            $hm    = $this->offsetToHM($offset);
            $first = true;

            \asort($group, \SORT_STRING);

            foreach ($group as $zone => $value) {
                try {
                    if ($first) {
                        $format    = new IntlDateFormatter(__('lang_identifier'), IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, $zone);
                        $options[] = ['(UTC' . $hm . ') ' . $format->format($now)];
                        $first     = false;
                    }

                    $options[] = [$zone, \trim($value)];
                } catch (IntlException $e) {
                }
            }
        }

        return $options;
    }

    protected function offsetToHM(int $offset) : string
    {
        return $offset < 0 ? '-' . \gmdate('H:i', -$offset) : '+' . \gmdate('H:i', $offset);
    }
}
