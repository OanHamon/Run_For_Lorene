@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- PNG : Gmail et Outlook n'affichent pas le SVG. Export fidèle de public/images/logo-run-for-lorene.svg. --}}
<img src="{{ asset('images/logo-run-for-lorene.png') }}" class="logo" width="90" height="92" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
