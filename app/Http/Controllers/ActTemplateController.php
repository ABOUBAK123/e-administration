<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\IssuingAdministration;
use App\Models\TemplateVariable;
use App\Services\Templates\TemplateGenerationCoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function currentAdministrationId(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if ($user->role === 'admin' && empty($user->profile_id)) {
            return null;
        }

        $profile = $user->profile_id ? \App\Models\AdministrationProfile::find($user->profile_id) : null;

        return $profile && $profile->administration_id
            ? (string) $profile->administration_id
            : null;
    }

    private function scopeTemplates()
    {
        $query = DocumentTemplate::query()->with(['variables', 'administration']);

        $adminId = $this->currentAdministrationId();
        if ($adminId) {
            $query->where('administration_id', $adminId);
        }

        return $query;
    }

    private function syncVariablesFromContent(DocumentTemplate $template): void
    {
        $content = (string) ($template->content ?? '');
        if ($content === '') {
            return;
        }

        $core = app(TemplateGenerationCoreService::class);
        $keys = $core->extractContentVariables($content);

        foreach ($keys as $key => $label) {
            $existing = $template->variables()->where('key', $key)->first();
            if ($existing) {
                continue;
            }

            $template->variables()->create([
                'key' => $key,
                'label' => $label,
                'field_type' => 'text',
                'required' => false,
                'placeholder' => '',
                'default_value' => '',
                'options' => [],
            ]);
        }
    }

    public function index()
    {
        $templates = $this->scopeTemplates()->latest()->get();

        return view('act-templates.index', compact('templates'));
    }

    public function create()
    {
        $administrations = IssuingAdministration::query()->orderBy('name')->get();

        return view('act-templates.create', compact('administrations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'in:docx,xlsx,pptx,pdf'],
            'content' => ['nullable', 'string'],
            'administration_id' => ['nullable', 'uuid'],
        ]);

        $adminId = $validated['administration_id'] ?? $this->currentAdministrationId();
        if ($adminId && !IssuingAdministration::query()->whereKey($adminId)->exists()) {
            abort(404, 'Administration introuvable.');
        }

        $template = DocumentTemplate::query()->create([
            'name' => $validated['name'],
            'file_name' => Str::slug($validated['name']) . '.' . $validated['file_type'],
            'file_type' => $validated['file_type'],
            'storage_path' => null,
            'content' => $validated['content'] ?? '',
            'administration_id' => $adminId,
            'created_by' => Auth::id(),
        ]);

        $this->syncVariablesFromContent($template);

        return redirect()->route('act-templates.index')->with('success', 'Modèle d’acte enregistré.');
    }

    public function show(DocumentTemplate $actTemplate)
    {
        $actTemplate->loadMissing(['variables', 'administration']);

        return view('act-templates.show', compact('actTemplate'));
    }

    public function edit(DocumentTemplate $actTemplate)
    {
        $actTemplate->loadMissing(['variables', 'administration']);
        $administrations = IssuingAdministration::query()->orderBy('name')->get();

        return view('act-templates.edit', compact('actTemplate', 'administrations'));
    }

    public function update(Request $request, DocumentTemplate $actTemplate)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'in:docx,xlsx,pptx,pdf'],
            'content' => ['nullable', 'string'],
            'administration_id' => ['nullable', 'uuid'],
        ]);

        $actTemplate->update([
            'name' => $validated['name'],
            'file_type' => $validated['file_type'],
            'content' => $validated['content'] ?? '',
            'administration_id' => $validated['administration_id'] ?? $actTemplate->administration_id,
        ]);

        $actTemplate->variables()->delete();
        $this->syncVariablesFromContent($actTemplate);

        return redirect()->route('act-templates.index')->with('success', 'Le modèle d’acte a été mis à jour.');
    }

    public function destroy(DocumentTemplate $actTemplate)
    {
        $actTemplate->variables()->delete();
        $actTemplate->delete();

        return redirect()->route('act-templates.index')->with('success', 'Le modèle d’acte a été supprimé.');
    }

    public function generateForm(DocumentTemplate $actTemplate)
    {
        $actTemplate->loadMissing(['variables', 'administration']);

        return view('act-templates.generate', [
            'template' => $actTemplate,
            'variables' => $actTemplate->variables()->orderBy('label')->get(),
            'rendered' => null,
        ]);
    }

    public function generate(Request $request, DocumentTemplate $actTemplate)
    {
        $request->validate([
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string'],
        ]);

        $actTemplate->loadMissing('variables');
        $core = app(TemplateGenerationCoreService::class);

        $values = $request->input('values', []);
        $core->assertRequiredValues($actTemplate, $values);

        $rendered = (string) ($actTemplate->content ?? '');
        foreach ($actTemplate->variables as $variable) {
            $key = (string) $variable->key;
            if ($key === '') {
                continue;
            }

            $value = $values[$key] ?? '';
            $rendered = str_replace(['{{ ' . $key . ' }}', '{{' . $key . '}}', '[' . $key . ']'], (string) $value, $rendered);
        }

        return view('act-templates.generate', [
            'template' => $actTemplate,
            'variables' => $actTemplate->variables()->orderBy('label')->get(),
            'rendered' => $rendered,
            'submittedValues' => $values,
        ]);
    }
}
