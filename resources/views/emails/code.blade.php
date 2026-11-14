{{-- Code à usage unique (connexion participant ou admin). Pas d'indentation : c'est du Markdown. --}}
<x-mail::message>
# Bonjour {{ $prenom }},

{{ $intro }}

<table class="code" align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="code-cell">
<span class="code-chiffres">{{ $code }}</span>
</td>
</tr>
</table>

<p class="validite">Il est valable {{ $minutes }} minutes.</p>

Si tu n'es pas à l'origine de cette demande, tu peux ignorer cet email.

À très vite,<br>
L'équipe Run For Lorène
</x-mail::message>
