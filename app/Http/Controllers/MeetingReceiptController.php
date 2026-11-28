<?php

namespace App\Http\Controllers;

use App\Models\MeetingMinutesReceipt;

/**
 * Page publique du lien « J'accuse réception ». L'ouverture du lien n'enregistre rien :
 * les antivirus de messagerie visitent les liens, seul le clic sur le bouton confirme.
 */
class MeetingReceiptController extends Controller
{
    public function show(string $token)
    {
        $receipt = $this->find($token);

        return view('meetings.receipt', ['receipt' => $receipt, 'meeting' => $receipt->meeting]);
    }

    public function confirm(string $token)
    {
        $receipt = $this->find($token);

        // La première confirmation fait foi.
        MeetingMinutesReceipt::whereKey($receipt->id)->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);

        return redirect()->route('meetings.receipt.show', ['token' => $token]);
    }

    private function find(string $token): MeetingMinutesReceipt
    {
        $receipt = MeetingMinutesReceipt::with('meeting')->where('token', $token)->first();
        abort_if(!$receipt || !$receipt->meeting, 404, 'Lien d\'accusé de réception inconnu.');

        return $receipt;
    }
}
