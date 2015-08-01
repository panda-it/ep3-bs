<?php

namespace Calendar\View\Helper\Cell;

use Zend\View\Helper\AbstractHelper;

class CellLogic extends AbstractHelper
{

    public function __invoke($walkingDate, $walkingTime, $timeBlock, $now, $square, $user, $reservationsForCol, $eventsForCol)
    {
        return sprintf('<td>%s</td>',
            $this->determineCell($walkingDate, $walkingTime, $timeBlock, $now, $square, $user, $reservationsForCol, $eventsForCol));
    }

    protected function determineCell($walkingDate, $walkingTime, $timeBlock, $now, $square, $user, $reservationsForCol, $eventsForCol)
    {
        $view = $this->getView();

        if ($walkingDate <= $now) {
            if (! ($user && $user->can('calendar.see-past'))) {
                return $view->calendarCell('Past', 'cc-over');
            }
        }

        if ($walkingTime < $square->needExtra('time_start_sec') || $walkingTime >= $square->needExtra('time_end_sec')) {
            return $view->calendarCell('Closed', 'cc-over');
        }

        $reservationsForCell = $view->calendarReservationsForCell($reservationsForCol, $square);
        $eventsForCell = $view->calendarEventsForCell($eventsForCol, $square);

        $timeBlockSplit = round($timeBlock / 2);

        if ($timeBlockSplit >= $square->need('time_block_bookable') || $eventsForCell) {

            $walkingTimeSplit = $walkingTime + $timeBlockSplit;

            $reservationsForFirstHalf = array();
            $reservationsForSecondHalf = array();

            foreach ($reservationsForCell as $rid => $reservation) {
                if ($reservation->needExtra('time_end_sec') <= $walkingTimeSplit || $reservation->needExtra('time_start_sec') < $walkingTimeSplit) {
                    $reservationsForFirstHalf[$rid] = $reservation;
                }

                if ($reservation->needExtra('time_start_sec') >= $walkingTimeSplit || $reservation->needExtra('time_end_sec') > $walkingTimeSplit) {
                    $reservationsForSecondHalf[$rid] = $reservation;
                }
            }

            $eventsForFirstHalf = array();
            $eventsForSecondHalf = array();

            foreach ($eventsForCell as $eid => $event) {
                if ($event->needExtra('date_start') == $walkingDate->format('Y-m-d')) {
                    if ($event->needExtra('time_start_sec') < $walkingTimeSplit) {
                        $eventsForFirstHalf[$eid] = $event;
                    }
                } else {
                    $eventsForFirstHalf[$eid] = $event;
                }

                if ($event->needExtra('date_end') == $walkingDate->format('Y-m-d')) {
                    if ($event->needExtra('time_end_sec') > $walkingTimeSplit) {
                        $eventsForSecondHalf[$eid] = $event;
                    }
                } else {
                    $eventsForSecondHalf[$eid] = $event;
                }
            }

            $firstHalf = $view->calendarCellRenderCell($walkingDate, $walkingTime, $timeBlockSplit, $square, $user,
                $reservationsForFirstHalf, $eventsForFirstHalf);
            $firstHalfUnified = preg_replace('/ts=[0-9:]{5}\&te=[0-9:]{5}/', '', $firstHalf);

            $walkingDate->modify('+' . $timeBlockSplit . ' sec');

            $secondHalf = $view->calendarCellRenderCell($walkingDate, $walkingTime + $timeBlockSplit, $timeBlockSplit, $square, $user,
                $reservationsForSecondHalf, $eventsForSecondHalf);
            $secondHalfUnified = preg_replace('/ts=[0-9:]{5}\&te=[0-9:]{5}/', '', $secondHalf);

            $walkingDate->modify('-' . $timeBlockSplit . ' sec');

            if ($firstHalfUnified == $secondHalfUnified) {
                $timeEnd = gmdate('H:i', $walkingTime + $timeBlock);

                if ($timeEnd == '00:00') {
                    $timeEnd = '24:00';
                }

                return preg_replace('/te=[0-9:]{5}/', 'te=' . $timeEnd, $firstHalf);
            } else {
                return sprintf('%s%s',
                    str_replace('calendar-cell', 'calendar-cell cc-height-2', $firstHalf),
                    str_replace('calendar-cell', 'calendar-cell cc-height-2', $secondHalf));
            }
        } else {
            return $view->calendarCellRenderCell($walkingDate, $walkingTime, $timeBlock, $square, $user, $reservationsForCell, $eventsForCell);
        }
    }

}
