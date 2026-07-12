<?php

namespace App\Enums;

enum NotificationType: string
{
    case MemberCreated = 'member_created';
    case MembershipRenewed = 'membership_renewed';
    case MembershipExpired = 'membership_expired';
}
