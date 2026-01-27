<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Procedure;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\PricingSetting;
use Illuminate\Support\Facades\Hash;

class QxLogTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ======================
        // ADMINS
        // ======================
        $super = User::firstOrCreate(
            ['username' => 'thealejandro'],
            [
                'name' => 'Alejandro',
                'username' => 'thealejandro',
                'email' => 'thealejandro7w7@gmail.com',
                'phone' => 30683865,
                'role' => 'admin',
                'is_super_admin' => true,
                'use_pay_scheme' => false,
                'password' => Hash::make('9977'),
            ]
        );

        $admin = User::firstOrCreate(
            ['username' => 'hospital'],
            [
                'name' => 'Administrador Hospital',
                'email' => 'hospitalcoban@gmail.com',
                'phone' => 77903000,
                'role' => 'admin',
                'is_super_admin' => false,
                'use_pay_scheme' => false,
                'password' => Hash::make('1981'),
            ]
        );

        // ======================
        // INSTRUMENTISTAS
        // ======================
        $inst1 = User::create([
            'name' => 'Ana Instrumentista',
            'username' => 'ana',
            'email' => 'ana@qxlog.test',
            'password' => Hash::make('123456'),
            'role' => 'instrumentist',
            'use_pay_scheme' => true,
        ]);

        $inst2 = User::create([
            'name' => 'Carlos Instrumentista',
            'username' => 'carlos',
            'email' => 'carlos@qxlog.test',
            'password' => Hash::make('123456'),
            'role' => 'instrumentist',
            'use_pay_scheme' => false,
        ]);

        // ======================
        // MÉDICOS
        // ======================
        $doc1 = User::create([
            'name' => 'Dr. Juan Pérez',
            'username' => 'juan',
            'email' => 'jperez@qxlog.test',
            'password' => Hash::make('123456'),
            'role' => 'doctor',
        ]);

        $doc2 = User::create([
            'name' => 'Dra. María López',
            'username' => 'maria',
            'email' => 'mlopez@qxlog.test',
            'password' => Hash::make('123456'),
            'role' => 'doctor',
        ]);

        // ======================
        // CIRCULANTES
        // ======================
        $circ1 = User::create([
            'name' => 'Pedro Circulante',
            'username' => 'pedro',
            'email' => 'pedro@qxlog.test',
            'password' => Hash::make('123456'),
            'role' => 'circulating',
        ]);

        // ======================
        // PROCEDIMIENTOS
        // ======================
        $procedures = [];

        for ($i = 1; $i <= 12; $i++) {
            $procedures[] = Procedure::create([
                'procedure_date' => now()->subDays(rand(1, 10))->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_minutes' => 120,
                'patient_name' => "Paciente {$i}",
                'procedure_type' => fake()->randomElement([
                    'Cesárea',
                    'Apendicectomía',
                    'Histerectomía',
                    'Colecistectomía',
                ]),
                'is_videosurgery' => fake()->boolean(30),

                'instrumentist_id' => $inst1->id,
                'instrumentist_name' => $inst1->name,

                'doctor_id' => $doc1->id,
                'doctor_name' => $doc1->name,

                'circulating_id' => $circ1->id,
                'circulating_name' => $circ1->name,

                'calculated_amount' => fake()->randomElement([200, 300, 400]),
                'pricing_snapshot' => [
                    'test' => true,
                ],

                'status' => 'pending',
            ]);
        }

        // ======================
        // PAGO YA REALIZADO
        // ======================
        $batch = PayoutBatch::create([
            'instrumentist_id' => $inst1->id,
            'paid_by_id' => $admin->id,
            'paid_at' => now()->subDays(2),
            'total_amount' => 600,
            'status' => 'active',
        ]);

        $paid = array_slice($procedures, 0, 3);

        foreach ($paid as $p) {
            PayoutItem::create([
                'payout_batch_id' => $batch->id,
                'procedure_id' => $p->id,
                'amount' => $p->calculated_amount,
                'snapshot' => [
                    'procedure_id' => $p->id,
                ],
            ]);

            $p->update([
                'status' => 'paid',
                'paid_at' => $batch->paid_at,
                'payout_batch_id' => $batch->id,
            ]);
        }

        PricingSetting::firstOrCreate(['id' => 1], [
            'default_rate' => 200,
            'video_rate' => 300,
            'night_rate' => 350,
            'long_case_rate' => 350,
            'long_case_threshold_minutes' => 120,
            'night_start' => '22:00',
            'night_end' => '06:00',
        ]);

    }
}
