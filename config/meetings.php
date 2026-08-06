<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rappels et alertes automatiques - Module Reunions
    |--------------------------------------------------------------------------
    |
    | Ces valeurs pilotent la commande planifiee `meetings:send-reminders`
    | (voir routes/console.php). Elles sont surchageables via .env.
    |
    */

    // Nombre d'heures avant le debut de la reunion pour envoyer le rappel
    // aux participants invites.
    'reminder_hours_before_meeting' => (int) env('MEETING_REMINDER_HOURS_BEFORE', 24),

    // Duree (en heures) au-dela de laquelle une demande de validation du
    // compte rendu est consideree "en retard" et declenche une relance.
    'validation_stale_hours' => (int) env('MEETING_VALIDATION_STALE_HOURS', 48),

    // Intervalle minimum (en heures) entre deux relances successives envoyees
    // au validateur tant que le compte rendu n'est pas valide.
    'validation_reminder_interval_hours' => (int) env('MEETING_VALIDATION_REMINDER_INTERVAL_HOURS', 48),

    // Nombre d'heures avant l'echeance de traitement (processing_deadline)
    // pour alerter l'organisateur si le compte rendu n'est pas encore publie.
    'deadline_alert_hours_before' => (int) env('MEETING_DEADLINE_ALERT_HOURS_BEFORE', 24),
];
