<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Rechnung.php';

/**
 * Die elektronische Rechnung (FatturaPA, Formato FPR12) zu einer Rechnung
 * oder Gutschrift (07.10.2026, Uwe: Vorschlag 10 „ja“).
 *
 * In Italien ist seit 2024 auch für Forfettari die XML-Datei die gültige
 * Rechnung; das PDF ist nur die Höflichkeitskopie. Diese Klasse ERZEUGT die
 * Datei — übermittelt wird sie von Uwe oder dem Commercialista über einen
 * SDI-Dienst. Nichts geht von hier automatisch an das SDI.
 *
 * Belege (ohne P.IVA) bekommen keine XML-Datei.
 */
final class FatturaPa
{
    /** @return array{name:string, xml:string} */
    public static function erzeugen(int $invoiceId): array
    {
        $r = Db::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$r) { throw new RuntimeException('Dokument nicht gefunden.'); }
        if (($r['doc_typ'] ?? 'beleg') === 'beleg' || (($r['doc_typ'] ?? '') === 'gutschrift' && ($r['steuerfall'] ?? 'beleg') === 'beleg')) {
            throw new RuntimeException('Ein Zahlungsbeleg ist keine Rechnung — dazu gibt es keine FatturaPA.');
        }
        $piva = preg_replace('~\D~', '', Firma::get('piva'));
        if ($piva === '') { throw new RuntimeException('Ohne Partita IVA keine FatturaPA.'); }
        require_once __DIR__ . '/Kunde.php';
        $k = Kunde::belegEmpfaenger($r);
        $kundeRoh = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]) ?: [];
        $k += $kundeRoh;
        $iso = Rechnung::landIso((string) ($k['country'] ?? ''));
        $fall = (string) ($r['steuerfall'] ?? 'ordinario');
        $natura = $r['natura'] ?? ($fall === 'forfettario' ? 'N2.2' : ($fall === 'reverse_charge' ? 'N2.1' : null));
        $satz = $natura !== null ? 0.0 : (float) $r['tax_rate'];
        if ($natura === null && $satz <= 0) { throw new RuntimeException('Rechnung ohne IVA-Satz und ohne Natura — so nimmt die Agenzia sie nicht an. Erst den Satz eintragen.'); }
        $prog = strtoupper(str_pad(base_convert((string) $invoiceId, 10, 36), 5, '0', STR_PAD_LEFT));

        $x = new XMLWriter();
        $x->openMemory(); $x->setIndent(true); $x->setIndentString('  ');
        $x->startDocument('1.0', 'UTF-8');
        $x->startElementNs('p', 'FatturaElettronica', 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2');
        $x->writeAttribute('versione', 'FPR12');
        $x->writeAttributeNs('xmlns', 'ds', null, 'http://www.w3.org/2000/09/xmldsig#');
        $x->writeAttributeNs('xmlns', 'xsi', null, 'http://www.w3.org/2001/XMLSchema-instance');

        $x->startElement('FatturaElettronicaHeader');
        $x->startElement('DatiTrasmissione');
        self::el($x, 'IdTrasmittente', ['IdPaese' => 'IT', 'IdCodice' => Firma::get('steuernr') !== '' ? strtoupper(Firma::get('steuernr')) : $piva]);
        $x->writeElement('ProgressivoInvio', $prog);
        $x->writeElement('FormatoTrasmissione', 'FPR12');
        $sdi = strtoupper(trim((string) ($k['sdi'] ?? '')));
        $pec = '';
        if (str_contains($sdi, '@')) { $pec = strtolower($sdi); $sdi = ''; }
        $x->writeElement('CodiceDestinatario', $iso !== 'IT' ? 'XXXXXXX' : (preg_match('~^[A-Z0-9]{7}$~', $sdi) ? $sdi : '0000000'));
        if ($iso === 'IT' && $pec !== '') { $x->writeElement('PECDestinatario', $pec); }
        $x->endElement();

        /* Wir: Ditta individuale — Nome/Cognome aus dem Inhaber. */
        $x->startElement('CedentePrestatore');
        $x->startElement('DatiAnagrafici');
        self::el($x, 'IdFiscaleIVA', ['IdPaese' => 'IT', 'IdCodice' => $piva]);
        if (Firma::get('steuernr') !== '') { $x->writeElement('CodiceFiscale', strtoupper(Firma::get('steuernr'))); }
        $inh = preg_split('~\s+~', trim(Firma::get('inhaber', 'Uwe Vetter'))) ?: ['Uwe', 'Vetter'];
        $cognome = count($inh) > 1 ? (string) array_pop($inh) : (string) $inh[0];
        $x->startElement('Anagrafica');
        $x->writeElement('Nome', self::t(implode(' ', $inh) ?: $cognome, 60));
        $x->writeElement('Cognome', self::t($cognome, 60));
        $x->endElement();
        $x->writeElement('RegimeFiscale', Firma::regime() === 'forfettario' ? 'RF19' : 'RF01');
        $x->endElement();
        self::sede($x, Firma::get('strasse', '-'), Firma::get('plz', '00000'), Firma::get('ort'), 'IT');
        $x->endElement();

        /* Kunde */
        $x->startElement('CessionarioCommittente');
        $x->startElement('DatiAnagrafici');
        $vat = strtoupper(preg_replace('~[^A-Z0-9]~i', '', (string) ($k['vat_id'] ?? '')));
        if ($vat !== '') {
            $paese = preg_match('~^[A-Z]{2}~', $vat) ? substr($vat, 0, 2) : $iso;
            $codice = preg_match('~^[A-Z]{2}~', $vat) ? substr($vat, 2) : $vat;
            self::el($x, 'IdFiscaleIVA', ['IdPaese' => $paese === 'EL' ? 'GR' : $paese, 'IdCodice' => $codice]);
        }
        if (trim((string) ($k['tax_code'] ?? '')) !== '' && $iso === 'IT') { $x->writeElement('CodiceFiscale', strtoupper(trim((string) $k['tax_code']))); }
        elseif ($vat === '') { $x->writeElement('CodiceFiscale', $iso === 'IT' ? '0000000000000000' : '99999999999'); }
        $x->startElement('Anagrafica');
        $firma = trim((string) ($k['company'] ?? ''));
        if ($firma !== '') { $x->writeElement('Denominazione', self::t($firma, 80)); }
        else {
            $n = preg_split('~\s+~', trim((string) ($k['name'] ?? 'Cliente'))) ?: ['Cliente'];
            $cg = count($n) > 1 ? (string) array_pop($n) : (string) $n[0];
            $x->writeElement('Nome', self::t(implode(' ', $n) ?: $cg, 60));
            $x->writeElement('Cognome', self::t($cg, 60));
        }
        $x->endElement();
        $x->endElement();
        self::sede($x, (string) ($k['street'] ?? '-'), (string) ($k['zip'] ?? ''), (string) ($k['city'] ?? ''), $iso);
        $x->endElement();
        $x->endElement();   // Header

        $x->startElement('FatturaElettronicaBody');
        $x->startElement('DatiGenerali');
        $x->startElement('DatiGeneraliDocumento');
        $x->writeElement('TipoDocumento', ($r['doc_typ'] ?? '') === 'gutschrift' ? 'TD04' : 'TD01');
        $x->writeElement('Divisa', (string) $r['currency']);
        $x->writeElement('Data', (string) $r['issued_at']);
        $x->writeElement('Numero', (string) $r['invoice_no']);
        if ((int) ($r['bollo_cents'] ?? 0) > 0) {
            $x->startElement('DatiBollo'); $x->writeElement('BolloVirtuale', 'SI'); $x->writeElement('ImportoBollo', self::b((int) $r['bollo_cents'])); $x->endElement();
        }
        $x->writeElement('ImportoTotaleDocumento', self::b((int) $r['total_cents']));
        $causale = ($r['doc_typ'] ?? '') === 'gutschrift' ? 'Nota di credito: ' . (string) ($r['grund'] ?? '') : Rechnung::wofuer((string) $r['art'], 'it');
        $x->writeElement('Causale', self::t($causale, 200));
        $x->endElement();
        if (($r['doc_typ'] ?? '') === 'gutschrift' && !empty($r['storno_von'])) {
            $o = Db::one('SELECT invoice_no, issued_at FROM invoices WHERE id = ?', [(int) $r['storno_von']]);
            if ($o) { $x->startElement('DatiFattureCollegate'); $x->writeElement('IdDocumento', (string) $o['invoice_no']); $x->writeElement('Data', (string) $o['issued_at']); $x->endElement(); }
        }
        $x->endElement();   // DatiGenerali

        $x->startElement('DatiBeniServizi');
        $posten = Rechnung::posten($r, 'it');
        $summeNetto = 0;
        foreach ($posten as $i => $p) {
            $netto = $natura !== null ? (int) $p['brutto'] : (int) $p['netto'];
            $summeNetto += $netto;
            $x->startElement('DettaglioLinee');
            $x->writeElement('NumeroLinea', (string) ($i + 1));
            $x->writeElement('Descrizione', self::t((string) $p['text'], 1000));
            $x->writeElement('Quantita', '1.00');
            $x->writeElement('PrezzoUnitario', self::b($netto));
            $x->writeElement('PrezzoTotale', self::b($netto));
            $x->writeElement('AliquotaIVA', number_format($satz, 2, '.', ''));
            if ($natura !== null) { $x->writeElement('Natura', $natura); }
            $x->endElement();
        }
        $x->startElement('DatiRiepilogo');
        $x->writeElement('AliquotaIVA', number_format($satz, 2, '.', ''));
        if ($natura !== null) { $x->writeElement('Natura', $natura); }
        $x->writeElement('ImponibileImporto', self::b($summeNetto));
        $x->writeElement('Imposta', self::b($natura !== null ? 0 : (int) $r['tax_cents']));
        if ($natura === null) { $x->writeElement('EsigibilitaIVA', 'I'); }
        $x->writeElement('RiferimentoNormativo', self::t($natura === 'N2.2' ? 'Operazione effettuata ai sensi dell\'art. 1, commi 54-89, L. 190/2014 - regime forfettario'
            : ($natura === 'N2.1' ? 'Operazione non soggetta ad IVA ai sensi dell\'art. 7-ter DPR 633/1972' : 'IVA ordinaria'), 100));
        $x->endElement();
        $x->endElement();   // DatiBeniServizi

        if (($r['doc_typ'] ?? '') !== 'gutschrift') {
            $x->startElement('DatiPagamento');
            $x->writeElement('CondizioniPagamento', 'TP02');
            $x->startElement('DettaglioPagamento');
            $x->writeElement('ModalitaPagamento', Firma::get('iban') !== '' ? 'MP05' : 'MP08');
            $x->writeElement('DataScadenzaPagamento', (string) ($r['due_at'] ?: $r['issued_at']));
            $x->writeElement('ImportoPagamento', self::b((int) $r['total_cents']));
            if (Firma::get('iban') !== '') { $x->writeElement('IBAN', strtoupper(preg_replace('~\s+~', '', Firma::get('iban')))); }
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();   // Body
        $x->endElement();   // FatturaElettronica
        $x->endDocument();
        return ['name' => 'IT' . $piva . '_' . $prog . '.xml', 'xml' => $x->outputMemory()];
    }

    private static function el(XMLWriter $x, string $name, array $kinder): void
    {
        $x->startElement($name);
        foreach ($kinder as $k => $v) { $x->writeElement($k, $v); }
        $x->endElement();
    }

    private static function sede(XMLWriter $x, string $strasse, string $cap, string $ort, string $iso): void
    {
        $prov = '';
        if (preg_match('~\(([A-Z]{2})\)~', $ort, $m)) { $prov = $m[1]; $ort = trim(str_replace($m[0], '', $ort)); }
        $x->startElement('Sede');
        $x->writeElement('Indirizzo', self::t($strasse !== '' ? $strasse : '-', 60));
        $x->writeElement('CAP', $iso === 'IT' && preg_match('~^\d{5}$~', $cap) ? $cap : '00000');
        $x->writeElement('Comune', self::t($ort !== '' ? $ort : '-', 60));
        if ($iso === 'IT' && $prov !== '') { $x->writeElement('Provincia', $prov); }
        $x->writeElement('Nazione', $iso);
        $x->endElement();
    }

    /** Betrag mit Punkt und zwei Stellen. */
    private static function b(int $cent): string { return number_format($cent / 100, 2, '.', ''); }

    /** Text auf die erlaubte Länge, nur druckbare Zeichen (Latin-1-Raum, wie das SDI es annimmt). */
    private static function t(string $s, int $max): string
    {
        $s = preg_replace('~[\x00-\x1F\x7F]~u', ' ', $s) ?? $s;
        $s = (string) (iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s) ?: $s);
        $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        return mb_substr(trim($s), 0, $max);
    }
}
