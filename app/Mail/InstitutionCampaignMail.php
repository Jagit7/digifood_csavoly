<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstitutionCampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly EmailCampaignRecipient $recipient
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // A subject sima szöveg (levéltárgy), NEM HTML - itt nem
            // szabad htmlspecialchars()-t futtatni a behelyettesített
            // értékeken, mert azzal elrontanánk a megjelenített tárgysort
            // (pl. egy "&" karakterből "&amp;" lenne).
            subject: $this->replacePlaceholders($this->recipient->campaign->subject, escapeHtml: false),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.institution-campaign',
            with: [
                'institutionName' => $this->recipient->campaign->institution->name,
                // A safe_body accessor már tisztított HTML-t ad vissza
                // (ld. EmailCampaign::getSafeBodyAttribute() /
                // App\Support\Html\CampaignHtmlSanitizer) - a nyers
                // "body" mezőt itt sosem szabad közvetlenül használni.
                'bodyHtml' => $this->replacePlaceholders($this->replaceDynamicUrls($this->recipient->campaign->safe_body), escapeHtml: true),
            ],
        );
    }

    private function replaceDynamicUrls(string $value): string
    {
        return match ($this->recipient->campaign->type) {
            EmailCampaign::TYPE_PARENT_ACTIVATION_INVITE => $this->replaceActivationUrl(
                $value,
                route('parent.activation.create'),
                route('parent.activation.create', [], false),
                ['#SZULOI_FIOK_AKTIVALASA_URL#', '#SZÜLŐI_FIÓK_AKTIVÁLÁSA_URL#']
            ),
            EmailCampaign::TYPE_EMPLOYEE_ACTIVATION_INVITE => $this->replaceActivationUrl(
                $value,
                route('employee.activation.create'),
                route('employee.activation.create', [], false),
                ['#DOLGOZOI_FIOK_AKTIVALASA_URL#', '#DOLGOZÓI_FIÓK_AKTIVÁLÁSA_URL#']
            ),
            default => $value,
        };
    }

    /**
     * A szülői/dolgozói aktivációs kampányokat korábban abszolút URL-lel
     * mentettük el az email_campaigns.body mezőbe. Ha akkor rossz volt az
     * APP_URL, a hibás host később is bent maradt volna a kiküldött levélben.
     * Ezt itt, célzottan a megfelelő route aktuális URL-jére normalizáljuk.
     *
     * @param  array<int, string>  $placeholders
     */
    private function replaceActivationUrl(string $value, string $absoluteUrl, string $relativePath, array $placeholders): string
    {
        $value = str_ireplace($placeholders, $absoluteUrl, $value);

        $escapedPath = preg_quote($relativePath, '~');

        return preg_replace(
            "~https?://[^\\s\"'<>]+{$escapedPath}(?:\\?[^\\s\"'<>]*)?~iu",
            $absoluteUrl,
            $value
        ) ?? $value;
    }

    /**
     * A behelyettesített értékek (gondviselő/gyermek/osztály neve) részben
     * a szülő saját maga által megadott, szabadon szerkeszthető adatok
     * (ld. ParentAccountService::updatePersonalData()). A HTML törzsbe
     * kerülő változatnál htmlspecialchars()-szel escape-eljük őket, hogy
     * egy ilyen névből ne nyílhasson egy második, a sanitizertől független
     * XSS-útvonal - a sima szöveges tárgysornál viszont ez nem kell (és
     * el is rontaná a megjelenítést).
     */
    private function replacePlaceholders(string $value, bool $escapeHtml): string
    {
        $defaultRecipientName = $this->recipient->institution_employee_id !== null
            ? 'Tisztelt Kolléga'
            : 'Tisztelt Szülő / Gondviselő';

        $recipientName = $this->recipient->recipient_name ?: $defaultRecipientName;
        $childNames = $this->recipient->childNamesList();
        $classGroupNames = $this->recipient->classGroupNamesList();
        $institutionName = $this->recipient->campaign->institution->name;

        if ($escapeHtml) {
            $recipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
            $childNames = htmlspecialchars($childNames, ENT_QUOTES, 'UTF-8');
            $classGroupNames = htmlspecialchars($classGroupNames, ENT_QUOTES, 'UTF-8');
            $institutionName = htmlspecialchars($institutionName, ENT_QUOTES, 'UTF-8');
        }

        // #DOLGOZO_NEVE# a dolgozói aktivációs meghívóknál használt,
        // additív helyőrző - ugyanazt az értéket adja, mint a #SZULO_NEVE#
        // (mindkettő a recipient_name mezőből jön), csak a dolgozói
        // sablonokban szemantikusan pontosabb elnevezéssel. A meglévő
        // szülői kampányokat ez nem érinti.
        //
        // A helyőrzőket az admin szabadon szerkesztheti/átírhatja a saját
        // egyedi szövegében (ld. a "Használható helyőrzők" panelt), ahol
        // könnyen elgépelheti a pontos formát - pl. kisbetűvel, vagy a
        // magyar helyesírás szerinti ékezetes alakban (pl. "DOLGOZÓ" a
        // "DOLGOZO" helyett, "SZÜLŐ" a "SZULO" helyett). Ezért a keresést
        // kis-/nagybetű-független (str_ireplace) módon végezzük, ÉS a
        // legvalószínűbb ékezetes elgépelési variánsokat is elfogadjuk -
        // így egy ilyen apró eltérés miatt a helyőrző nem marad
        // behelyettesítetlenül a kiküldött e-mailben.
        return str_ireplace(
            [
                '#SZULO_NEVE#', '#SZÜLŐ_NEVE#',
                '#DOLGOZO_NEVE#', '#DOLGOZÓ_NEVE#',
                '#GYERMEK_NEVE#',
                '#OSZTALY#', '#OSZTÁLY#',
                '#INTEZMENY_NEVE#', '#INTÉZMÉNY_NEVE#',
            ],
            [
                $recipientName, $recipientName,
                $recipientName, $recipientName,
                $childNames,
                $classGroupNames, $classGroupNames,
                $institutionName, $institutionName,
            ],
            $value
        );
    }
}
