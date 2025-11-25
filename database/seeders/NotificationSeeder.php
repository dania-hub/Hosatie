<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        Notification::create([
            'user_id'  => 5,
            'title'    => 'صرف الدواء متاح',
            'message'  => 'يرجى مراجعة الصيدلية لصرف الدواء',
            'type'     => 'عادي',
            'is_read'  => false,
        ]);
    }
}
