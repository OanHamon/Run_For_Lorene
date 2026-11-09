{{--
    Billet imprimable (PDF A4, dompdf) : l'empreinte des semelles est le billet, le QR code classique
    sert de secours, le numéro de dossard permet de retrouver la personne par recherche au retrait.
    dompdf ne lit ni Tailwind ni flexbox : tableaux, CSS simple, couleurs de App\Support\Charte.
--}}
@php
    use App\Support\Charte;
    $c = Charte::COULEURS;
    $course = $inscription->tarif->course;
    $personne = $inscription->participant->user;
    $depart = $course->heure_depart ? \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') : null;
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Billet Run For Lorène · {{ $personne->prenom }} {{ $personne->nom }}</title>
<style>
    @font-face { font-family: 'Varela Round'; src: url('{{ resource_path('fonts/VarelaRound-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ resource_path('fonts/Carlito-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ resource_path('fonts/Carlito-Bold.ttf') }}') format('truetype'); }

    @page { margin: 12mm 15mm 10mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 11.5pt; line-height: 1.45; color: {{ $c['vert-sombre'] }}; margin: 0; }
    .titre { font-family: 'Varela Round', sans-serif; font-weight: normal; color: {{ $c['vert'] }}; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 0; }
    .gris { color: {{ $c['gris-texte'] }}; }
    .ruban { display: inline-block; background: {{ $c['vert-doux'] }}; color: #fff; font-family: 'Varela Round', sans-serif; font-size: 12pt; padding: 1.6mm 6mm; border-radius: 10mm; }
    .date { display: inline-block; background: {{ $c['vert'] }}; color: {{ $c['moutarde'] }}; font-family: 'Varela Round', sans-serif; font-size: 11.5pt; padding: 1.6mm 5mm; border-radius: 10mm; }
    .carte { border: 0.4mm solid {{ $c['bordure'] }}; border-radius: 7mm; padding: 5mm; text-align: center; }
    .dossard { background: {{ $c['vert'] }}; border-radius: 7mm; padding: 5mm 4mm 6mm; text-align: center; }
    .pastille { display: inline-block; width: 3.4mm; height: 3.4mm; border-radius: 1.7mm; border: 0.3mm solid {{ $c['bordure'] }}; }
    .consignes { background: {{ $c['vert-clair'] }}; border-radius: 7mm; padding: 4mm 7mm; margin-top: 5mm; }
    .consignes td { padding: 0.8mm 0; }
    .num { font-family: 'Varela Round', sans-serif; color: {{ $c['vert'] }}; font-size: 13pt; line-height: 1.1; }
    .pied { margin-top: 6mm; }
    .pied td { line-height: 1.35; }
</style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 40mm;"><img src="{{ public_path('images/logo-run-for-lorene.svg') }}" alt="Run for Lorène" style="height: 22mm;"></td>
            <td style="text-align: right; padding-top: 4mm;">
                <div class="titre" style="font-size: 15pt;">{{ $inscription->evenement->nom }}</div>
                <div style="margin-top: 2.5mm;"><span class="date">{{ ucfirst($inscription->evenement->date_evenement->translatedFormat('l j F Y')) }}</span></div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 5mm;"><span class="ruban">Ton billet</span></div>
    <div class="titre" style="font-size: 26pt; line-height: 1.2; margin-top: 3mm;">{{ $personne->prenom }} {{ $personne->nom }}</div>
    <div style="font-size: 13pt; margin-top: 1.5mm;">
        @if ($course->couleur)<span class="pastille" style="background: {{ $course->couleur }};"></span>&nbsp;@endif{{ $course->nom }}@if ($depart) · départ {{ $depart }}@endif
    </div>

    <table style="margin-top: 5mm;">
        <tr>
            <td style="width: 108mm; padding-right: 6mm;">
                <div class="carte">
                    <img src="{{ $empreinte }}" alt="Empreinte : le billet à scanner" style="width: 84mm;">
                    <div class="gris" style="font-size: 10pt; margin-top: 2mm;">Ton empreinte : c'est elle qu'on scanne au retrait.</div>
                </div>
            </td>
            <td>
                <div class="dossard">
                    <div style="color: #fff; font-size: 11pt;">Dossard</div>
                    @if ($inscription->dossard)
                        <div style="font-family: 'Varela Round', sans-serif; color: {{ $c['moutarde'] }}; font-size: 40pt; line-height: 1.1;">{{ $inscription->dossard }}</div>
                    @else
                        <div style="color: #fff; font-size: 11pt; margin-top: 2mm;">Numéro communiqué par l'association</div>
                    @endif
                </div>
                <div class="carte" style="margin-top: 5mm; padding: 3mm;">
                    <img src="{{ $qr }}" alt="QR code de secours" style="width: 36mm;">
                    <div class="gris" style="font-size: 9.5pt;">QR code de secours</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="consignes">
        <div class="titre" style="font-size: 13pt; margin-bottom: 1.5mm;">Le jour J, au retrait des dossards</div>
        <table>
            <tr><td style="width: 7mm;"><div class="num">1</div></td><td>Présente ce billet, imprimé ou sur ton téléphone.</td></tr>
            <tr><td><div class="num">2</div></td><td>Un ou une bénévole scanne ton empreinte. Si elle ne passe pas, il ou elle scanne le QR code de secours.</td></tr>
            <tr><td><div class="num">3</div></td><td>Pas de billet sous la main ? Donne ton nom ou ton numéro de dossard.</td></tr>
        </table>
    </div>

    <table class="pied">
        <tr>
            <td class="gris" style="font-size: 9pt; vertical-align: bottom;">
                Billet personnel de {{ $personne->prenom }} {{ $personne->nom }}, à ne pas partager.<br>
                Inscription n° {{ $inscription->id_inscription }} · organisée par l'association Effervescence Jeunes.
            </td>
            <td style="width: 34mm; text-align: right; vertical-align: bottom;">
                <img src="{{ public_path('images/logo-effervescence-jeunes.png') }}" alt="Effervescence Jeunes" style="width: 30mm;">
            </td>
        </tr>
    </table>
</body>
</html>
