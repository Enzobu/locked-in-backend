<?php

namespace App\Service;

use App\Entity\Locker;

final class ReservationPricingService
{
    private const OVERTIME_GRACE_MINUTES = 5;
    private const OVERTIME_STEP_MINUTES = 15;

    public function computePlannedAmountCents(Locker $locker, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): int
    {
        $durationMinutes = max(1, (int) ceil(($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60));
        $hourlyPriceCents = max(0, (int) $locker->getPriceCents());

        return (int) ceil(($durationMinutes * $hourlyPriceCents) / 60);
    }

    /**
     * @return array{overtimeMinutes:int,overtimeAmountCents:int,billableMinutes:int}
     */
    public function computeOvertime(Locker $locker, \DateTimeImmutable $plannedEndsAt, \DateTimeImmutable $actualEndsAt): array
    {
        $overtimeMinutes = max(0, (int) ceil(($actualEndsAt->getTimestamp() - $plannedEndsAt->getTimestamp()) / 60));
        $billableMinutes = max(0, $overtimeMinutes - self::OVERTIME_GRACE_MINUTES);

        if ($billableMinutes === 0) {
            return [
                'overtimeMinutes' => $overtimeMinutes,
                'billableMinutes' => 0,
                'overtimeAmountCents' => 0,
            ];
        }

        $steps = (int) ceil($billableMinutes / self::OVERTIME_STEP_MINUTES);
        $stepPriceCents = (int) ceil(max(0, (int) $locker->getPriceCents()) / 4);
        $baseOvertimeAmountCents = $steps * $stepPriceCents;

        $surchargePercent = max(0, (int) ($locker->getLockerBay()?->getOvertimeSurchargePercent() ?? 0));
        $overtimeAmountCents = (int) ceil($baseOvertimeAmountCents * (100 + $surchargePercent) / 100);

        return [
            'overtimeMinutes' => $overtimeMinutes,
            'billableMinutes' => $billableMinutes,
            'overtimeAmountCents' => $overtimeAmountCents,
        ];
    }
}
