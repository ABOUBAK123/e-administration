<?php

namespace Tests\Feature;

use App\Models\ActRequestSubmission;
use App\Models\AdministrationSmtpSetting;
use App\Models\IssuingAdministration;
use App\Models\RequestedAct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActRequestStatusSmtpNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_email_uses_administration_smtp_configuration(): void
    {
        Mail::fake();
        Log::spy();

        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère des actes',
            'code' => 'MIN-ACT-' . Str::random(6),
            'is_active' => true,
        ]);

        $requestedAct = RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'direction_code' => 'DIR-ACT',
            'document_name' => 'Acte de test',
            'motif' => 'Motif de test',
            'required_documents' => [],
            'applicant_fields' => [],
            'auto_generate_enabled' => false,
            'is_active' => true,
        ]);

        AdministrationSmtpSetting::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.actes.example.test',
            'mail_port' => 587,
            'mail_username' => 'noreply@example.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@example.test',
            'mail_from_name' => 'E-Administration',
        ]);

        $submission = ActRequestSubmission::create([
            'id' => (string) Str::uuid(),
            'requested_act_id' => $requestedAct->id,
            'emitter_administration_id' => $administration->id,
            'direction_code' => 'DIR-ACT',
            'requested_document_name' => 'Acte de test',
            'applicant_full_name' => 'Demandeur Test',
            'applicant_email' => 'demandeur@example.test',
            'applicant_phone' => '0600000000',
            'status' => 'pending',
            'tracking_number' => 'ACT-2026-001',
            'tracking_token' => 'token-123',
        ]);

        $submission->update(['status' => 'in_progress']);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.actes.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
        Log::shouldNotHaveReceived('error');
    }
}
