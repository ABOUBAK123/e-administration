<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppSettingController extends Controller
{
    private const DEFRAG_RECOMMENDED = [
        'template_defrag_max_xml_bytes' => 1500000,
        'template_defrag_max_paragraph_bytes' => 60000,
    ];

    public function index()
    {
        $settings = AppSetting::all()->keyBy('key');

        $thresholdSettings = [
            'template_defrag_max_xml_bytes' => [
                'label' => 'Seuil XML max avant mode safe (bytes)',
                'description' => 'Si le XML depasse ce seuil, la defragmentation regex est contournee.',
                'default' => 1200000,
                'min' => 200000,
                'max' => 20000000,
            ],
            'template_defrag_max_paragraph_bytes' => [
                'label' => 'Seuil paragraphe max avant mode safe (bytes)',
                'description' => 'Si un paragraphe depasse ce seuil, il est ignore par la defragmentation regex.',
                'default' => 45000,
                'min' => 2000,
                'max' => 500000,
            ],
        ];

        return view('admin.settings', compact('settings', 'thresholdSettings'));
    }

    /** Clés autorisées à être modifiées via ce contrôleur. */
    private const ALLOWED_KEYS = [
        'app_name', 'app_public_url', 'app_logo',
        'onlyoffice_server_url', 'onlyoffice_secret', 'onlyoffice_doc_viewer',
        'mail_from_address', 'mail_from_name',
        'qr_image_page', 'qr_image_x', 'qr_image_y', 'qr_image_width', 'qr_image_height',
        'signature_qr_position',
        'theme_primary_color', 'theme_secondary_color', 'theme_logo',
        'courrier_archival_days',
        'email_notifications_enabled', 'email_notifications_from',
        'chat_enabled', 'chat_scope',
        'template_defrag_max_xml_bytes', 'template_defrag_max_paragraph_bytes',
    ];

    public function update(Request $request)
    {
        // Réservé aux administrateurs
        abort_if(
            !auth()->check() || auth()->user()->role !== 'admin',
            403,
            'Accès réservé aux administrateurs.'
        );

        $restoreRequested = (string) $request->input('restore_defrag_defaults', '') === '1';
        if ($restoreRequested) {
            $request->merge(self::DEFRAG_RECOMMENDED);
        }

        $validated = $request->validate([
            'template_defrag_max_xml_bytes' => 'nullable|integer|min:200000|max:20000000',
            'template_defrag_max_paragraph_bytes' => 'nullable|integer|min:2000|max:500000',
        ]);

        $filtered = array_filter(
            $request->except('_token', '_method'),
            fn($key) => in_array($key, self::ALLOWED_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );

        // Le serveur OnlyOffice global n'est pas modifiable par un admin rattaché à un profil.
        if (auth()->user()->profile_id) {
            unset($filtered['app_public_url'], $filtered['onlyoffice_server_url'], $filtered['onlyoffice_secret'], $filtered['onlyoffice_doc_viewer']);
        }

        foreach (array_keys($validated) as $k) {
            if (array_key_exists($k, $filtered) && ($filtered[$k] === null || $filtered[$k] === '')) {
                unset($filtered[$k]);
            }
        }

        foreach ($filtered as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['id' => Str::uuid(), 'value' => $value]
            );
        }

        $message = $restoreRequested
            ? 'Valeurs recommandees restaurees pour les seuils templates.'
            : 'Paramètres enregistrés.';

        return back()->with('success', $message);
    }

    public function get(string $key)
    {
        $setting = AppSetting::where('key', $key)->first();
        return response()->json(['value' => $setting?->value]);
    }
}
