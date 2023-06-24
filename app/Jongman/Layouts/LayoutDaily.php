<?php

namespace App\Jongman\Layouts;

use App\Jongman\Contracts\LayoutDailyInterface;
use App\Jongman\Contracts\LayoutScheduleInterface;
use App\Jongman\SchedulePeriodSpanable;
use App\Jongman\Contracts\ReservationListingInterface;
use Illuminate\Support\Carbon;

class LayoutDaily implements LayoutDailyInterface
{	/**
    * @var ReservationListingInterface
    */
   private $_reservationListing;
   /**
    * @var LayoutScheduleInterface
    */
   private $_scheduleLayout;

   /**
    * @param ReservationListingInterface $listing List of reservation data for schedule
    * @param LayoutScheduleInterface $layout schedule layout blocks
    */
   public function __construct(ReservationListingInterface $listing, LayoutScheduleInterface $layout)
   {
       // Just store the provided data
       $this->_reservationListing = $listing;
       $this->_scheduleLayout = $layout;
   }

   /**
    * Get display slots for resource specified by $resourceId on date specified by $date
    * @see IDailyLayout::getLayout()
    */
   public function getLayout(Carbon $date, $resourceId)
   {
       $hideBlocked = false;
       $items = $this->_reservationListing->onDateForResource($date, $resourceId);

       $list = new ScheduleReservationList($items, $this->_scheduleLayout, $date, $hideBlocked);
       $slots = $list->buildSlots();

       return $slots;
   }
   
   /**
    * check if the provided date is reservable
    * just check if the provided date is past or not
    * @see IDailyLayout::isDateReservable()
    */
   public function isDateReservable(Carbon $date)
   {
       return !$date->getDate()->lessThan(Carbon::now()->getDate());
   }

   public function getLabels(Carbon $displayDate)
   {
       $hideBlocked = false;

       $labels = array();

       $periods = $this->_scheduleLayout->getLayout($displayDate, $hideBlocked);

       if ($periods[0]->beginsBefore($displayDate))
       {
            // first period starts before displaying date
            $labels[] = $periods[0]->label($displayDate->getDate());
       }
       else
       {
           $labels[] = $periods[0]->label();
       }

       for ($i = 1; $i < count($periods); $i++)
       {
           $labels[] = $periods[$i]->label();
       }

       return $labels;
   }

   /**
    * Get periods on the date for the current schedule
    * @see IDailyLayout::getPeriods()
    */
   public function getPeriods(Carbon $displayDate, $fitToHours = false)
   {
       $hideBlocked = false;

       $periods = $this->_scheduleLayout->getLayout($displayDate, $hideBlocked);

       if (!$fitToHours)
       {
           return $periods;
       }

       /** @var $periodsToReturn SpanablePeriod[] */
       $periodsToReturn = array();

       for ($i = 0; $i < count($periods); $i++)
       {
           $span = 1;
           $currentPeriod = $periods[$i];
           $periodStart = $currentPeriod->beginDate();
           $periodLength = $periodStart->getDifference($currentPeriod->endDate())->hours();

           if (!$periods[$i]->isLabelled() && ($periodStart->minute() == 0 && $periodLength < 1))
           {
               $span = 0;
               $nextPeriodTime = $periodStart->addMinutes(60);
               $tempPeriod = $currentPeriod;
               while ($tempPeriod != null && $tempPeriod->beginDate()->lessThan($nextPeriodTime))
               {
                   $span++;
                   $i++;
                   $tempPeriod = $periods[$i];
               }
               $i--;

           }
           $periodsToReturn[] = new SchedulePeriodSpanable($currentPeriod, $span);

       }

       return $periodsToReturn;
   }
}