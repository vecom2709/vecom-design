<?php
declare(strict_types=1);

/**
 * Alle Status an einer Stelle. Projektstatus und Website-Status sind
 * bewusst getrennt: eine Website kann offline sein, waehrend das Projekt
 * "Fertig" ist — und umgekehrt.
 */
final class Status
{
    public const BESTELLUNG = [
        'neu'                => 'Neu',
        'zahlung_ausstehend' => 'Zahlung ausstehend',
        'bezahlt'            => 'Bezahlt',
        'onboarding'         => 'Onboarding',
        'in_bearbeitung'     => 'In Bearbeitung',
        'feedback'           => 'Feedback',
        'aenderungen'        => 'Änderungen',
        'fertig'             => 'Fertig',
        'abgeschlossen'      => 'Abgeschlossen',
        'storniert'          => 'Storniert',
    ];

    public const ZAHLUNG = [
        'ausstehend'         => 'Ausstehend',
        'in_bearbeitung'     => 'In Bearbeitung',
        'bezahlt'            => 'Bezahlt',
        'fehlgeschlagen'     => 'Fehlgeschlagen',
        'abgebrochen'        => 'Abgebrochen',
        'rueckerstattet'     => 'Rückerstattet',
        'teilweise_erstattet'=> 'Teilweise erstattet',
    ];

    /** Reihenfolge = Fortschritt. Der Index bestimmt den Prozentwert. */
    public const PROJEKT = [
        'bestellung_eingegangen' => 'Bestellung eingegangen',
        'zahlung_bestaetigt'     => 'Zahlung bestätigt',
        'onboarding'             => 'Onboarding',
        'informationen_erhalten' => 'Informationen erhalten',
        'design'                 => 'Design',
        'entwicklung'            => 'Entwicklung',
        'vorschau'               => 'Vorschau',
        'kundenfeedback'         => 'Kundenfeedback',
        'aenderungen'            => 'Änderungen',
        'finale_freigabe'        => 'Finale Freigabe',
        'veroeffentlichung'      => 'Veröffentlichung',
        'online'                 => 'Online',
        'abgeschlossen'          => 'Abgeschlossen',
    ];

    public const WEBSITE = [
        'nicht_veroeffentlicht' => 'Noch nicht veröffentlicht',
        'wird_geprueft'         => 'Wird geprüft',
        'online'                => 'Online',
        'offline'               => 'Offline',
        'fehler'                => 'Fehler',
        'ssl_problem'           => 'SSL-Problem',
        'domain_problem'        => 'Domain-Problem',
    ];

    public const INTEGRATION = [
        'verbunden'        => 'Verbunden',
        'nicht_verbunden'  => 'Nicht verbunden',
        'fehler'           => 'Fehler',
        'deaktiviert'      => 'Deaktiviert',
    ];

    /** Farbklasse fuer das Status-Abzeichen. */
    public static function ton(string $status): string
    {
        return match ($status) {
            'bezahlt', 'online', 'abgeschlossen', 'fertig', 'verbunden'       => 'gut',
            'fehlgeschlagen', 'offline', 'fehler', 'storniert', 'abgebrochen',
            'ssl_problem', 'domain_problem'                                    => 'schlecht',
            'ausstehend', 'zahlung_ausstehend', 'feedback', 'kundenfeedback',
            'aenderungen', 'wird_geprueft'                                     => 'warnung',
            default                                                            => 'neutral',
        };
    }

    public static function label(array $liste, ?string $wert): string
    {
        if ($wert === null) { return '—'; }
        return $liste[$wert] ?? $wert;
    }

    /** Fortschritt in Prozent aus dem Projektstatus. */
    public static function fortschritt(string $projektStatus): int
    {
        $keys = array_keys(self::PROJEKT);
        $i = array_search($projektStatus, $keys, true);
        if ($i === false) { return 0; }
        return (int) round($i / (count($keys) - 1) * 100);
    }
}
