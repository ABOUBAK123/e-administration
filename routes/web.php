<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\SharedTemplateController;
use App\Http\Controllers\ActRequestController;
use App\Http\Controllers\PublicActRequestController;
use App\Http\Controllers\QrVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\MeetingAttendanceController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\SessionDebugController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\IssuingAdministrationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CourrierController;

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/mot-de-passe-oublie',  [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'sendResetLink'])->name('password.email');

    // Double authentification (OTP par email)
    Route::get('/verification-otp',  [AuthController::class, 'showTwoFactorForm'])->name('2fa.show');
    Route::post('/verification-otp', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify');
    Route::post('/verification-otp/renvoyer', [AuthController::class, 'resendTwoFactor'])->name('2fa.resend');
});

// Changement de langue public (accessible sans authentification)
Route::post('/lang/{locale}', function ($locale) {
    $allowed = ['fr', 'en', 'es', 'pt', 'ar'];
    if (in_array($locale, $allowed)) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
    return redirect()->back();
})->name('lang.switch');

// Routes publiques (sans authentification) — fichiers vierges pour OnlyOffice
Route::get('/oo-blank/{type}', function ($type) {
    $allowed = ['docx' => 'empty_template.docx', 'xlsx' => 'blank_xlsx.xlsx', 'pptx' => 'blank_pptx.pptx'];
    if (!isset($allowed[$type])) { abort(404); }
    $path = public_path($allowed[$type]);
    if (!file_exists($path)) { abort(404); }
    $mimes = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    return response()->file($path, [
        'Content-Type'                => $mimes[$type],
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control'               => 'no-store',
    ]);
})->name('oo.blank');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// URL signée temporaire pour permettre à OnlyOffice de récupérer le fichier sans session
Route::get('/documents/{document}/onlyoffice-file', [DocumentController::class, 'onlyofficeFile'])
    ->name('documents.onlyofficeFile');

// Routes de débogage disponibles uniquement en local
if (app()->environment(['local', 'development'])) {
    Route::get('/debug/locale', [DebugController::class, 'testLocale'])->name('debug.locale');
    Route::get('/test/session-debug', [SessionDebugController::class, 'index'])->name('test.session');
    Route::post('/test/locale/{locale}', [SessionDebugController::class, 'setLocaleTest'])->name('test.setLocale');
}

// Téléchargement sécurisé d'un document partagé en externe
Route::get('/shared-files/{share}', [DocumentController::class, 'sharedDownload'])
    ->name('documents.shared-download');

// Alias pour les environnements servis sous sous-dossier (ex: WAMP /e-administration_laravel)
Route::get('/e-administration_laravel/documents/{document}/onlyoffice-file', [DocumentController::class, 'onlyofficeFile'])
    ->name('documents.onlyofficeFile.subdir');
Route::get('/e-administration_laravel/public/documents/{document}/onlyoffice-file', [DocumentController::class, 'onlyofficeFile'])
    ->name('documents.onlyofficeFile.subdir.public');
Route::get('/public/documents/{document}/onlyoffice-file', [DocumentController::class, 'onlyofficeFile'])
    ->name('documents.onlyofficeFile.public');

// Webhook SunnyStamp (sans session, sans CSRF, appelé par la plateforme externe)
// Le middleware verify.webhook valide le token secret embarqué dans l'URL.
Route::post('/api/signature/platform-webhook', [SignatureController::class, 'platformWebhook'])
    ->middleware('verify.webhook')
    ->name('signature.platform-webhook');

// Alias webhooks en sous-dossier
Route::post('/e-administration_laravel/api/signature/platform-webhook', [SignatureController::class, 'platformWebhook'])
    ->middleware('verify.webhook')
    ->name('signature.platform-webhook.subdir');
Route::post('/e-administration_laravel/public/api/signature/platform-webhook', [SignatureController::class, 'platformWebhook'])
    ->middleware('verify.webhook')
    ->name('signature.platform-webhook.subdir.public');
Route::post('/public/api/signature/platform-webhook', [SignatureController::class, 'platformWebhook'])
    ->middleware('verify.webhook')
    ->name('signature.platform-webhook.public');

// Diagnostic endpoints (sans auth, accès public pour support technique)
Route::get('/api/signature/diag/{executionId}', [SignatureController::class, 'diagExecution'])
    ->name('signature.diag');
Route::get('/e-administration_laravel/api/signature/diag/{executionId}', [SignatureController::class, 'diagExecution'])
    ->name('signature.diag.subdir');

// Alias callbacks OnlyOffice (utile en sous-dossier/ngrok)
Route::post('/api/oo-callback/document/{document}', [DocumentController::class, 'onlyofficeCallback'])
    ->name('oo.document.callback.web');
Route::post('/e-administration_laravel/api/oo-callback/document/{document}', [DocumentController::class, 'onlyofficeCallback'])
    ->name('oo.document.callback.web.subdir');
Route::post('/e-administration_laravel/public/api/oo-callback/document/{document}', [DocumentController::class, 'onlyofficeCallback'])
    ->name('oo.document.callback.web.subdir.public');
Route::post('/public/api/oo-callback/document/{document}', [DocumentController::class, 'onlyofficeCallback'])
    ->name('oo.document.callback.web.public');

// URL signée de fichier document pour téléchargement par OnlyOffice (sans session)
Route::get('/api/oo-file/document/{document}', [DocumentController::class, 'onlyofficeFile'])
    ->name('oo.document.file.web');
Route::get('/e-administration_laravel/api/oo-file/document/{document}', [DocumentController::class, 'onlyofficeFile'])
    ->name('oo.document.file.web.subdir');
Route::get('/e-administration_laravel/public/api/oo-file/document/{document}', [DocumentController::class, 'onlyofficeFile'])
    ->name('oo.document.file.web.subdir.public');
Route::get('/public/api/oo-file/document/{document}', [DocumentController::class, 'onlyofficeFile'])
    ->name('oo.document.file.web.public');

Route::post('/api/oo-callback/template/{templateId}', [AdminController::class, 'ooTemplateCallback'])
    ->name('oo.template.callback.web');
Route::post('/e-administration_laravel/api/oo-callback/template/{templateId}', [AdminController::class, 'ooTemplateCallback'])
    ->name('oo.template.callback.web.subdir');
Route::post('/e-administration_laravel/public/api/oo-callback/template/{templateId}', [AdminController::class, 'ooTemplateCallback'])
    ->name('oo.template.callback.web.subdir.public');
Route::post('/public/api/oo-callback/template/{templateId}', [AdminController::class, 'ooTemplateCallback'])
    ->name('oo.template.callback.web.public');

// URL signée de fichier template pour téléchargement par OnlyOffice (sans session)
Route::get('/api/oo-file/template/{templateId}', [AdminController::class, 'ooTemplateFile'])
    ->name('oo.template.file.web');
Route::get('/e-administration_laravel/api/oo-file/template/{templateId}', [AdminController::class, 'ooTemplateFile'])
    ->name('oo.template.file.web.subdir');
Route::get('/e-administration_laravel/public/api/oo-file/template/{templateId}', [AdminController::class, 'ooTemplateFile'])
    ->name('oo.template.file.web.subdir.public');

// Modèle de compte rendu des réunions – accès OnlyOffice (sans session)
Route::get('/meetings/{meeting}/template-file', [MeetingController::class, 'templateFile'])
    ->name('meetings.template.file');
Route::post('/api/oo-callback/meeting-template/{meeting}', [MeetingController::class, 'templateOoCallback'])
    ->name('meetings.template.oo.callback');
Route::get('/public/api/oo-file/template/{templateId}', [AdminController::class, 'ooTemplateFile'])
    ->name('oo.template.file.web.public');

// Demande d'acte publique (sans authentification)
Route::get('/demande-acte', [PublicActRequestController::class, 'index'])->name('public.act-requests.index');
Route::post('/demande-acte/rechercher', [PublicActRequestController::class, 'searchByTrackingNumber'])->name('public.act-requests.search');
Route::get('/demande-acte/suivi/{tracking_token}', [PublicActRequestController::class, 'track'])->name('public.act-requests.track');
Route::get('/demande-acte/{administration_id}', [PublicActRequestController::class, 'showActsByAdministration'])->name('public.act-requests.by-admin');
Route::get('/demande-acte/{administration_id}/{requested_act_id}', [PublicActRequestController::class, 'create'])->name('public.act-requests.create');
Route::post('/demande-acte/{administration_id}/{requested_act_id}', [PublicActRequestController::class, 'store'])->name('public.act-requests.store');

// Emargement public par QR code (sans authentification)
Route::get('/meetings/qr/{token}', [MeetingAttendanceController::class, 'showByToken'])->name('meetings.qr.show');
Route::post('/meetings/qr/{token}', [MeetingAttendanceController::class, 'signByToken'])->name('meetings.qr.sign');
Route::get('/meetings/qr/{token}/lookup', [MeetingAttendanceController::class, 'lookupByToken'])->name('meetings.qr.lookup');
Route::post('/meetings/qr/{token}/correct', [MeetingAttendanceController::class, 'correctByToken'])->name('meetings.qr.correct');

// Téléchargement public d'un document via token QR
Route::get('/qr-download/{token}', [QrVerificationController::class, 'downloadByToken'])->name('qr.download');

// Page de vérification publique (scannée depuis le QR du document)
Route::get('/qr/{token}', [QrVerificationController::class, 'publicLanding'])->name('qr.public');

// Application (auth requise)
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Gestion Courrier
    Route::prefix('courrier')->name('courrier.')->group(function () {
        Route::get('/tableau-de-bord',  [CourrierController::class, 'tableauDeBord'])->name('tableau-de-bord');
        Route::get('/enregistrement',   [CourrierController::class, 'enregistrement'])->name('enregistrement');
        Route::get('/envoi',            [CourrierController::class, 'envoi'])->name('envoi');
        Route::get('/reception',        [CourrierController::class, 'reception'])->name('reception');
        Route::get('/liste',            [CourrierController::class, 'liste'])->name('liste');
        Route::get('/imputation',       [CourrierController::class, 'imputation'])->name('imputation');
        Route::get('/en-traitement',    [CourrierController::class, 'enTraitement'])->name('en-traitement');
        Route::get('/suivi-imputation', [CourrierController::class, 'suiviImputation'])->name('suivi-imputation');
        Route::get('/traite',           [CourrierController::class, 'traite'])->name('traite');
        Route::get('/archives',         [CourrierController::class, 'archives'])->name('archives');
        Route::get('/visualiser',       [CourrierController::class, 'visualiser'])->name('visualiser');
        Route::post('/store',           [CourrierController::class, 'store'])->name('store');
        Route::put('/{courrier}',       [CourrierController::class, 'update'])->name('update');
        Route::post('/scan-ocr',        [CourrierController::class, 'scanOcr'])->name('scan-ocr');
        Route::post('/imputer',         [CourrierController::class, 'imputer'])->name('imputer');
        Route::post('/traiter',         [CourrierController::class, 'traiter'])->name('traiter');
        Route::post('/{courrier}/ok-traitement', [CourrierController::class, 'okTraitement'])->name('ok-traitement');
        Route::post('/{courrier}/valider-traitement', [CourrierController::class, 'validerTraitement'])->name('valider-traitement');
        Route::post('/{courrier}/rejeter-traitement', [CourrierController::class, 'rejeterTraitement'])->name('rejeter-traitement');
    });

    // Documents
    Route::post('/documents/upload-ajax', [DocumentController::class, 'uploadAjax'])->name('documents.uploadAjax');
    Route::post('/documents/new', [DocumentController::class, 'createNew'])->name('documents.new');
    Route::post('/documents/onlyoffice-config', [DocumentController::class, 'onlyofficeConfig'])->name('documents.onlyofficeConfig');
    Route::post('/documents/share/lookup-tracking', [DocumentController::class, 'lookupActRequestByTracking'])->name('documents.share.lookupTracking');
    Route::get('/documents/trash', [DocumentController::class, 'trash'])->name('documents.trash');
    Route::post('/documents/{id}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
    Route::delete('/documents/{id}/force-delete', [DocumentController::class, 'forceDelete'])->name('documents.forceDelete');
    Route::resource('documents', DocumentController::class);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/download-folder', [DocumentController::class, 'downloadFolder'])->name('documents.downloadFolder');
    Route::post('/documents/{document}/favorite', [DocumentController::class, 'toggleFavorite'])->name('documents.favorite');
    Route::post('/documents/{document}/labels', [DocumentController::class, 'updateLabels'])->name('documents.labels');
    Route::post('/documents/{document}/rename', [DocumentController::class, 'rename'])->name('documents.rename');
    Route::post('/documents/{document}/move', [DocumentController::class, 'move'])->name('documents.move');
    Route::post('/documents/{document}/share', [DocumentController::class, 'share'])->name('documents.share');
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions');
    Route::post('/documents/{document}/status', [DocumentController::class, 'changeStatus'])->name('documents.status');
    Route::post('/documents/{document}/validate-act-request', [DocumentController::class, 'validateGeneratedActRequest'])->name('documents.validate-act-request');
    Route::post('/documents/{document}/convert-pdf', [DocumentController::class, 'convertToPdf'])->name('documents.convertPdf');
    Route::post('/documents/{document}/paraphe', [DocumentController::class, 'paraphe'])->name('documents.paraphe');

    // Workflows
    Route::resource('workflows', WorkflowController::class);
    Route::post('/workflows/{workflow}/execute', [WorkflowController::class, 'execute'])->name('workflows.execute');
    Route::post('/workflows/{workflow}/advance', [WorkflowController::class, 'advance'])->name('workflows.advance');
    Route::post('/workflows/{workflow}/reject',  [WorkflowController::class, 'reject'])->name('workflows.reject');
    Route::post('/workflows/{workflow}/duplicate',[WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
    // Modèles de workflows
    Route::get('/workflow-templates',  [WorkflowController::class, 'indexTemplates'])->name('workflow-templates.index');
    Route::post('/workflow-templates', [WorkflowController::class, 'storeTemplate'])->name('workflow-templates.store');

    // Signatures
    Route::get('/signatures',                  [SignatureController::class, 'index'])->name('signatures.index');
    Route::post('/signatures',                 [SignatureController::class, 'store'])->name('signatures.store');
    Route::post('/signatures/request',         [SignatureController::class, 'request'])->name('signatures.request');
    // Upload & signer depuis l'ordinateur
    Route::get('/signatures/upload',           [SignatureController::class, 'showUpload'])->name('signatures.upload');
    Route::post('/signatures/upload',          [SignatureController::class, 'handleUpload'])->name('signatures.upload.post');
    Route::get('/signatures/{signature}',      [SignatureController::class, 'show'])->name('signatures.show');
    Route::post('/signatures/{signatureRequest}/decline', [SignatureController::class, 'decline'])->name('signatures.decline');
    // Positionnement de zone de signature (depuis mes documents)
    Route::get('/signatures/{signatureRequest}/position',  [SignatureController::class, 'position'])->name('signatures.position');
    Route::post('/signatures/{signatureRequest}/sign',     [SignatureController::class, 'sign'])->name('signatures.sign');
    // Actions workflow depuis la boîte de réception
    Route::post('/signatures/workflow-action',  [SignatureController::class, 'workflowAction'])->name('signatures.workflow-action');
    Route::post('/signatures/get-invite-url',   [SignatureController::class, 'getSignatureInviteUrl'])->name('signatures.get-invite-url');
    Route::get('/signatures/workflow-document/{executionId}', [SignatureController::class, 'serveWorkflowDocument'])->name('signatures.workflow-document');
    Route::get('/signatures/platform-status/{executionId}', [SignatureController::class, 'getPlatformWorkflowStatus'])->name('signatures.platform-status');
    Route::get('/signatures/signed-document/{executionId}', [SignatureController::class, 'serveSignedDocument'])->name('signatures.signed-document');
    // Création d'un workflow de signature
    Route::post('/signatures/workflow-create',  [SignatureController::class, 'workflowCreate'])->name('signatures.workflow-create');

    // Notifications
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/ajax-list',    [NotificationController::class, 'ajaxList'])->name('notifications.ajaxList');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/users', [ChatController::class, 'users'])->name('chat.users');
    Route::get('/chat/online-by-administration', [ChatController::class, 'onlineByAdministration'])->name('chat.online-by-administration');
    Route::get('/chat/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
    Route::post('/chat/heartbeat', [ChatController::class, 'heartbeat'])->name('chat.heartbeat');

    // Réception
    Route::get('/reception', [ReceptionController::class, 'index'])->name('reception.index');
    Route::post('/reception/{document}/forward', [ReceptionController::class, 'forward'])->name('reception.forward');
    Route::post('/reception/{document}/mark-received', [ReceptionController::class, 'markReceived'])->name('reception.mark-received');

    // Templates partagés
    Route::get('/shared-templates', [SharedTemplateController::class, 'index'])->name('shared-templates.index');
    Route::post('/shared-templates/{template}/generate', [SharedTemplateController::class, 'generate'])->name('shared-templates.generate');

    // Demandes d'actes
    Route::get('/act-requests', [ActRequestController::class, 'index'])->name('act-requests.index');
    Route::post('/act-requests/{submission}/attachments.zip', [ActRequestController::class, 'downloadAttachmentsZip'])
        ->name('act-requests.attachments.zip');

    // Reunions
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    // Backward-compat route name used by older compiled meeting views.
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'show'])->name('meetings.edit');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::post('/meetings/{meeting}/minutes', [MeetingController::class, 'updateMinutes'])->name('meetings.minutes.update');
    Route::post('/meetings/{meeting}/ai-minutes', [MeetingController::class, 'aiGenerateMinutes'])->name('meetings.ai.minutes');
    Route::post('/meetings/{meeting}/workflow', [MeetingController::class, 'workflow'])->name('meetings.workflow');
    Route::post('/meetings/{meeting}/template-oo-config', [MeetingController::class, 'templateOoConfig'])->name('meetings.template.oo.config');
        Route::post('/meetings/{meeting}/template-analyze', [MeetingController::class, 'analyzeTemplate'])->name('meetings.template.analyze');
        Route::post('/meetings/{meeting}/template-generate', [MeetingController::class, 'generateFromTemplate'])->name('meetings.template.generate');
    Route::get('/meetings-calendar', [MeetingController::class, 'calendar'])->name('meetings.calendar');
    Route::get('/meetings-reporting', [MeetingController::class, 'reporting'])->name('meetings.reporting');
    Route::get('/meetings-export/csv', [MeetingController::class, 'exportCsv'])->name('meetings.export.csv');
    Route::get('/meetings-export/pdf-summary', [MeetingController::class, 'exportSummaryPdf'])->name('meetings.export.pdf.summary');
    Route::get('/meetings/{meeting}/dashboard', [MeetingAttendanceController::class, 'dashboard'])->name('meetings.dashboard');
    Route::get('/meetings/{meeting}/attendance/download', [MeetingAttendanceController::class, 'downloadAttendance'])->name('meetings.attendance.download');

    // Salles de reunion
    Route::get('/meeting-rooms', [MeetingRoomController::class, 'index'])->name('meetings.rooms.index');
    Route::post('/meeting-rooms', [MeetingRoomController::class, 'store'])->name('meetings.rooms.store');
    Route::put('/meeting-rooms/{room}', [MeetingRoomController::class, 'update'])->name('meetings.rooms.update');
    Route::delete('/meeting-rooms/{room}', [MeetingRoomController::class, 'destroy'])->name('meetings.rooms.destroy');

    // Vérification QR
    Route::get('/qr-verification',        [QrVerificationController::class, 'index'])->name('qr-verification.index');
    Route::post('/qr-verification/verify', [QrVerificationController::class, 'verify'])->name('qr-verification.verify');
    Route::post('/qr-verification/verify-number', [QrVerificationController::class, 'verifyNumber'])->name('qr-verification.verify-number');

    // Profil
    Route::get('/profile',               [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',               [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar',       [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/display-name', [ProfileController::class, 'updateDisplayName'])->name('profile.display-name');
    Route::post('/profile/password',     [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/language',     [ProfileController::class, 'updateLanguage'])->name('profile.language');

    // Admin (page unifiée) — réservé aux utilisateurs avec rôle admin
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/',          [AdminController::class, 'index'])->name('index');
        Route::get('/emitters',  [AdminController::class, 'index'])->name('emitters.index');
        Route::get('/recipients', [AdminController::class, 'recipientsIndex'])->name('recipients.index');
        Route::get('/settings',  [AppSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [AppSettingController::class, 'update'])->name('settings.update');
        Route::put('/settings',  [AdminController::class, 'updateSettings'])->name('settings.save');
        Route::post('/smtp-settings',           [AdminController::class, 'saveAdminSmtp'])->name('smtp.settings.save');
        Route::get('/smtp-settings/{type}/{id}',[AdminController::class, 'getAdminSmtp'])->name('smtp.settings.get');
        Route::post('/smtp-test', [AdminController::class, 'testSmtp'])->name('smtp.test');
        Route::post('/theming',  [AdminController::class, 'saveTheming'])->name('theming.save');
        Route::post('/signature-provider', [AdminController::class, 'saveSignatureProvider'])->name('signature-provider.save');
        Route::post('/signature-provider/test', [AdminController::class, 'testSignatureConnection'])->name('signature-provider.test');
        Route::post('/onlyoffice-token', [AdminController::class, 'onlyofficeToken'])->name('onlyoffice.token');
        Route::middleware('admin:personnel.employees')->group(function () {
            Route::post('/personnel/employees', [AdminController::class, 'storePersonnelEmployee'])->name('personnel.employees.store');
            Route::get('/personnel/employees/template', [AdminController::class, 'downloadPersonnelEmployeesTemplate'])->name('personnel.employees.template');
            Route::post('/personnel/employees/import', [AdminController::class, 'importPersonnelEmployees'])->name('personnel.employees.import');
            Route::put('/personnel/employees/{employee}', [AdminController::class, 'updatePersonnelEmployee'])->name('personnel.employees.update');
            Route::post('/personnel/employees/{employee}/virtual-card/transmit', [AdminController::class, 'transmitVirtualCardForSignature'])->name('personnel.employees.virtual-card.transmit');
            Route::post('/personnel/employees/{employee}/create-user', [AdminController::class, 'createUserFromPersonnelEmployee'])->name('personnel.employees.create-user');
            Route::post('/personnel/employees/{employee}/documents', [AdminController::class, 'uploadPersonnelEmployeeDocument'])->name('personnel.employees.documents.store');
            Route::post('/personnel/employee-skills', [AdminController::class, 'storePersonnelEmployeeSkill'])->name('personnel.employees.skills.store');
            Route::get('/personnel/documents/{document}/download', [AdminController::class, 'downloadPersonnelEmployeeDocument'])->name('personnel.documents.download');
            Route::post('/personnel/job-references', [AdminController::class, 'storePersonnelJobReference'])->name('personnel.job-references.store');
            Route::post('/personnel/staffing-needs', [AdminController::class, 'storePersonnelStaffingNeed'])->name('personnel.staffing-needs.store');
            Route::put('/personnel/staffing-needs/{staffingNeed}', [AdminController::class, 'updatePersonnelStaffingNeed'])->name('personnel.staffing-needs.update');
            Route::delete('/personnel/staffing-needs/{staffingNeed}', [AdminController::class, 'destroyPersonnelStaffingNeed'])->name('personnel.staffing-needs.destroy');
        });

        Route::middleware('admin:personnel.leave')->group(function () {
            Route::post('/personnel/leave-types', [AdminController::class, 'storePersonnelLeaveType'])->name('personnel.leave-types.store');
            Route::put('/personnel/leave-types/{leaveType}', [AdminController::class, 'updatePersonnelLeaveType'])->name('personnel.leave-types.update');
            Route::delete('/personnel/leave-types/{leaveType}', [AdminController::class, 'destroyPersonnelLeaveType'])->name('personnel.leave-types.destroy');
            Route::get('/personnel/leave-types/{leaveType}/justification-zip', [AdminController::class, 'downloadPersonnelLeaveTypeJustificationZip'])->name('personnel.leave-types.justification-zip.download');
            Route::post('/personnel/leave-requests', [AdminController::class, 'storePersonnelLeaveRequest'])->name('personnel.leave-requests.store');
            Route::get('/personnel/leave-requests/{leaveRequest}/attachment', [AdminController::class, 'downloadPersonnelLeaveRequestAttachment'])->name('personnel.leave-requests.attachment.download');
            Route::patch('/personnel/leave-requests/{leaveRequest}/status', [AdminController::class, 'updatePersonnelLeaveRequestStatus'])->name('personnel.leave-requests.status');
        });

        Route::middleware('admin:personnel.training')->group(function () {
            Route::post('/personnel/trainings', [AdminController::class, 'storePersonnelTraining'])->name('personnel.trainings.store');
            Route::post('/personnel/training-enrollments', [AdminController::class, 'storePersonnelTrainingEnrollment'])->name('personnel.training-enrollments.store');
            Route::patch('/personnel/training-enrollments/{enrollment}/status', [AdminController::class, 'updatePersonnelTrainingEnrollmentStatus'])->name('personnel.training-enrollments.update-status');
        });

        Route::middleware('admin:personnel.career')->group(function () {
            Route::post('/personnel/goals', [AdminController::class, 'storePersonnelGoal'])->name('personnel.goals.store');
            Route::post('/personnel/performance-reviews', [AdminController::class, 'storePersonnelPerformanceReview'])->name('personnel.performance-reviews.store');
            Route::post('/personnel/career-events', [AdminController::class, 'storePersonnelCareerEvent'])->name('personnel.career-events.store');
            Route::get('/personnel/mutation-requests', function () {
                return redirect()->route('admin.index', [
                    'tab' => 'personnel',
                    'personnel_tab' => 'agent-space',
                    'agent_space_tab' => 'mutation',
                ]);
            })->name('personnel.mutation-requests.index');
            Route::post('/personnel/mutation-requests', [AdminController::class, 'storePersonnelMutationRequest'])->name('personnel.mutation-requests.store');
            Route::get('/personnel/mutation-requests/{event}/attachment/{attachmentIndex}', [AdminController::class, 'downloadPersonnelMutationRequestAttachment'])->name('personnel.mutation-requests.attachment.download');
            Route::patch('/personnel/mutation-requests/{event}/status', [AdminController::class, 'updatePersonnelMutationRequestStatus'])->name('personnel.mutation-requests.status');
        });
        Route::post('/templates/upload-file', [AdminController::class, 'uploadTemplateFile'])->name('admin.templates.uploadFile');
        Route::post('/templates/{template}/detect-vars', [AdminController::class, 'detectTemplateVars'])->name('admin.templates.detectVars');
            Route::post('/templates/{template}/ai-enrich',   [AdminController::class, 'aiEnrichTemplateVars'])->name('admin.templates.aiEnrich');
        Route::post('/templates/{template}/recover-vars', [AdminController::class, 'recoverTemplateVars'])->name('admin.templates.recoverVars');
        Route::get('/templates/{template}/save-state', [AdminController::class, 'templateSaveState'])->name('admin.templates.saveState');
        Route::get('/templates/{template}/oo-config', [AdminController::class, 'getTemplateOoConfig'])->name('admin.templates.ooConfig');

        // Émetteurs
        Route::post('/emitters',                 [AdminController::class, 'storeEmitter'])->name('emitters.store');
        Route::put('/emitters/{emitter}',        [AdminController::class, 'updateEmitter'])->name('emitters.update');
        Route::delete('/emitters/{emitter}',     [AdminController::class, 'destroyEmitter'])->name('emitters.destroy');

        // Destinataires
        Route::middleware('admin:administration.recipients')->group(function () {
            Route::post('/recipients',               [AdminController::class, 'storeRecipient'])->name('recipients.store');
            Route::put('/recipients/{recipient}',    [AdminController::class, 'updateRecipient'])->name('recipients.update');
            Route::delete('/recipients/{recipient}', [AdminController::class, 'destroyRecipient'])->name('recipients.destroy');
        });

        // Types de direction
        Route::middleware('admin:administration.direction-types')->group(function () {
            Route::post('/direction-types',                    [AdminController::class, 'storeDirectionType'])->name('direction-types.store');
            Route::put('/direction-types/{directionType}',     [AdminController::class, 'updateDirectionType'])->name('direction-types.update');
            Route::delete('/direction-types/{directionType}',  [AdminController::class, 'destroyDirectionType'])->name('direction-types.destroy');
        });

        // Templates
        Route::post('/templates',                                        [AdminController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{template}',                              [AdminController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}',                           [AdminController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::post('/templates/{template}/variables',                   [AdminController::class, 'storeTemplateVariable'])->name('templates.variables.store');
        Route::put('/templates/{template}/variables/{variableId}',       [AdminController::class, 'updateTemplateVariable'])->name('templates.variables.update');
        Route::delete('/templates/{template}/variables/{variableId}',    [AdminController::class, 'destroyTemplateVariable'])->name('templates.variables.destroy');
        Route::post('/templates/{template}/share',                       [AdminController::class, 'updateTemplateShare'])->name('templates.share');
        Route::post('/templates/{template}/zones',                       [AdminController::class, 'saveTemplateZones'])->name('templates.zones.save');
        Route::post('/templates/{template}/force-save',                  [AdminController::class, 'forceSaveTemplate'])->name('templates.forceSave');

        // Règles de routage
        Route::middleware('admin:administration.routing')->group(function () {
            Route::post('/routing-rules',               [AdminController::class, 'storeRoutingRule'])->name('routing-rules.store');
            Route::delete('/routing-rules/{routingRule}',[AdminController::class, 'destroyRoutingRule'])->name('routing-rules.destroy');
        });

        // Entités sous tutelle
        Route::middleware('admin:administration.sub-entities')->group(function () {
            Route::post('/sub-entities',                [AdminController::class, 'storeSubEntity'])->name('sub-entities.store');
            Route::put('/sub-entities/{subEntity}',     [AdminController::class, 'updateSubEntity'])->name('sub-entities.update');
            Route::delete('/sub-entities/{subEntity}',  [AdminController::class, 'destroySubEntity'])->name('sub-entities.destroy');
        });

        // Actes demandés
        Route::middleware('admin:administration.requested-acts')->group(function () {
            Route::post('/requested-acts',               [AdminController::class, 'storeRequestedAct'])->name('requested-acts.store');
            Route::put('/requested-acts/{requestedAct}', [AdminController::class, 'updateRequestedAct'])->name('requested-acts.update');
            Route::delete('/requested-acts/{requestedAct}', [AdminController::class, 'destroyRequestedAct'])->name('requested-acts.destroy');
        });

        // Types de dossiers État civil (paramétrage)
        Route::post('/civil-status-types',                     [AdminController::class, 'storeCivilStatusRecordType'])->name('civil-status-types.store');
        Route::put('/civil-status-types/{civilStatusRecordType}', [AdminController::class, 'updateCivilStatusRecordType'])->name('civil-status-types.update');
        Route::delete('/civil-status-types/{civilStatusRecordType}', [AdminController::class, 'destroyCivilStatusRecordType'])->name('civil-status-types.destroy');

        // Profils / Rôles
        Route::post('/profiles',            [AdminController::class, 'storeProfile'])->name('profiles.store');
        Route::post('/profiles/assign',     [AdminController::class, 'assignProfile'])->name('profiles.assign');
        Route::get('/profiles/{profile}',   function($profile) { return redirect()->route('admin.index', ['tab' => 'user-profiles', 'p_edit' => $profile]); })->name('profiles.show');
        Route::post('/profiles/{profile}',  [AdminController::class, 'updateProfile'])->name('profiles.post-update');
        Route::put('/profiles/{profile}',   [AdminController::class, 'updateProfile'])->name('profiles.update');
        Route::delete('/profiles/{profile}',[AdminController::class, 'destroyProfile'])->name('profiles.destroy');

        // Instructions de traitement courrier
        Route::middleware('admin:administration.instructions')->group(function () {
            Route::post('/instructions',                    [AdminController::class, 'storeInstruction'])->name('instructions.store');
            Route::put('/instructions/{instruction}',       [AdminController::class, 'updateInstruction'])->name('instructions.update');
            Route::delete('/instructions/{instruction}',    [AdminController::class, 'destroyInstruction'])->name('instructions.destroy');
        });

        // API Mobile Money
        Route::middleware('admin:administration.mobile-money')->group(function () {
            Route::post('/mobile-money',                    [AdminController::class, 'storeMobileMoneyConfig'])->name('mobile-money.store');
            Route::delete('/mobile-money/{mobileMoneyConfig}', [AdminController::class, 'destroyMobileMoneyConfig'])->name('mobile-money.destroy');
        });

        // Utilisateurs (onglet admin intégré)
        Route::post('/users-tab',                          [AdminController::class, 'storeUserTab'])->name('users-tab.store');
        Route::get('/users-tab/{user}',                    fn($user) => redirect()->route('admin.index', ['tab' => 'users', 'u_edit' => $user]))->name('users-tab.show');
        Route::put('/users-tab/{user}',                    [AdminController::class, 'updateUserTab'])->name('users-tab.update');
        Route::delete('/users-tab/{user}',                 [AdminController::class, 'destroyUserTab'])->name('users-tab.destroy');
        Route::post('/users-tab/{user}/toggle-status',     [AdminController::class, 'toggleUserStatusTab'])->name('users-tab.toggle-status');
        Route::post('/users-tab/{user}/toggle-two-factor', [AdminController::class, 'toggleUserTwoFactorTab'])->name('users-tab.toggle-two-factor');
        Route::post('/users-tab/{user}/notify-account',    [AdminController::class, 'notifyUserAccount'])->name('users-tab.notify-account');

        Route::resource('administrations', IssuingAdministrationController::class);
        Route::resource('users', UserController::class)->middleware('admin:administration.users');
    });
});
