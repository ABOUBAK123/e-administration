{!! $senderName !!}
{!! $isReminder ? 'RAPPEL' : 'COMPTE RENDU VALIDÉ' !!} : {!! $meetingTitle !!}

@if($isReminder)
Sauf erreur de notre part, nous n'avons pas encore reçu votre accusé de réception. Vous trouverez de nouveau les documents en pièces jointes.

@endif
@if($customBody !== null)
{!! $customBody !!}
@else
{!! $recipientName ? 'Bonjour ' . $recipientName . ',' : 'Bonjour,' !!}

Nous vous prions de bien vouloir trouver ci-joint le compte rendu validé ainsi que la liste de présence de la réunion mentionnée ci-dessous.
@endif

@foreach($details as $label => $value)
{!! $label !!} : {!! $value !!}
@endforeach

Pièces jointes :
@foreach($attachmentNames as $name)
- {!! $name !!}
@endforeach
@if($receiptUrl)

Merci de confirmer la bonne réception de ces documents avec votre lien personnel (ne le transférez pas) :
{!! $receiptUrl !!}
@endif
@if($customBody === null)

Veuillez agréer l'expression de nos salutations distinguées.

{!! $senderName !!}
@endif

--
@if($contact)
Pour toute question, contactez l'organisateur de la réunion : {!! $contact !!}.
@endif
Message envoyé automatiquement par {!! config('app.name') !!}.
