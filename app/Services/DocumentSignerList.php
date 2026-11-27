<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tous les signataires d'un document, pour la vérification d'authenticité.
 */
class DocumentSignerList
{
    /**
     * Un circuit n'enregistre une signature que pour sa dernière étape : les signataires précédents
     * sont retrouvés par leur demande de signature honorée. Seule une trace de signature compte —
     * une étape de workflow simplement franchie ne prouve pas que la personne a signé.
     *
     * @return list<array{id: string, name: string, signed_at: Carbon|null}> dans l'ordre des signatures
     */
    public function for(Document $document): array
    {
        $dates = []; // [signataire => date de sa dernière signature]
        $add = function ($userId, ?Carbon $date) use (&$dates): void {
            $userId = (string) $userId;
            if ($userId === '') {
                return;
            }
            if (!array_key_exists($userId, $dates) || ($date && (!$dates[$userId] || $date->gt($dates[$userId])))) {
                $dates[$userId] = $date;
            }
        };

        $signatures = Signature::where('document_id', $document->id)->get(['signer_id', 'signed_at', 'status', 'is_valid']);
        $isValid = fn (Signature $signature) => $signature->status === 'valid' && $signature->is_valid !== false;

        $signatures->filter($isValid)->each(fn ($signature) => $add($signature->signer_id, $signature->signed_at));

        // Une personne dont toutes les signatures ont été révoquées ou ont expiré n'est pas listée.
        $withdrawn = $signatures->reject($isValid)->pluck('signer_id')->map(fn ($id) => (string) $id)
            ->diff(array_keys($dates))->flip();

        SignatureRequest::where('document_id', $document->id)
            ->where('status', 'signed')
            ->get(['requested_to', 'responded_at'])
            ->reject(fn ($request) => $withdrawn->has((string) $request->requested_to))
            ->each(fn ($request) => $add($request->requested_to, $request->responded_at));

        // Les comptes supprimés restent des signataires du document.
        $users = User::withTrashed()->whereIn('id', array_keys($dates))->get(['id', 'name', 'full_name'])->keyBy('id');

        return collect($dates)
            ->map(fn (?Carbon $date, string $id) => [
                'id'        => $id,
                'name'      => (string) ($users->get($id)?->name ?: $users->get($id)?->full_name ?: 'Signataire'),
                'signed_at' => $date,
            ])
            ->sortBy(fn (array $signer) => [$signer['signed_at']?->getTimestamp() ?? PHP_INT_MAX, $signer['name']])
            ->values()
            ->all();
    }
}
