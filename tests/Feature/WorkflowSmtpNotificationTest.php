<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\IssuingAdministration;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowSmtpNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_step_advanced_uses_administration_smtp_configuration_for_assignee(): void
    {
        Mail::fake();
        Log::spy();

        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Administration workflow',
            'code' => 'WF-' . Str::random(5),
            'is_active' => true,
        ]);

        $profile = AdministrationProfile::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'name' => 'VALIDATEUR',
            'permissions' => ['menuPermissions' => ['workflows.validate']],
        ]);

        $assignee = User::factory()->create([
            'profile_id' => $profile->id,
            'email' => 'validator@example.test',
            'name' => 'Validateur Workflow',
        ]);

        UserDirectionAssignment::create([
            'id' => (string) Str::uuid(),
            'user_id' => $assignee->id,
            'direction_scope_type' => 'emitter',
            'direction_scope_id' => $administration->id,
            'sub_entity_code' => 'DIR-WF',
            'direction_label' => 'Direction workflow',
        ]);

        AdministrationSmtpSetting::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.workflow.test',
            'mail_port' => 587,
            'mail_username' => 'workflow@example.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'no-reply@example.test',
            'mail_from_name' => 'E-Administration',
        ]);

        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Validation workflow',
            'description' => 'Demande de validation',
            'status' => 'active',
            'created_by' => $assignee->id,
            'docs_to_sign' => [],
            'attached_docs' => [],
        ]);

        WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Validation',
            'type' => 'review',
            'assignee_id' => $assignee->id,
            'description' => 'Validation initiale',
            'requires_signature' => false,
        ]);

        WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Signature',
            'type' => 'sign',
            'assignee_id' => $assignee->id,
            'description' => 'Signature finale',
            'requires_signature' => true,
        ]);

        NotificationService::workflowStepAdvanced($workflow, 2, 'Agent responsable');

        Mail::assertSentCount(1);
        Mail::assertSent(function ($mail) use ($assignee) {
            return $mail->hasTo($assignee->email)
                && str_contains((string) $mail->subject, 'Validation workflow');
        });

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.workflow.test', config('mail.mailers.smtp.host'));
        Log::shouldNotHaveReceived('error');
    }

    public function test_workflow_completed_logs_explicit_error_when_smtp_configuration_missing(): void
    {
        Mail::fake();
        Log::spy();

        $creator = User::factory()->create([
            'email' => 'creator@example.test',
            'name' => 'Créateur',
        ]);

        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Workflow sans SMTP',
            'description' => 'Pas de config SMTP',
            'status' => 'active',
            'created_by' => $creator->id,
            'docs_to_sign' => [],
            'attached_docs' => [],
        ]);

        NotificationService::workflowCompleted($workflow, 'Agent');

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $creator->id,
            'title' => 'Workflow terminé',
        ]);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'WorkflowCompleted email failed')
                    && array_key_exists('error', $context);
            });
    }
}
