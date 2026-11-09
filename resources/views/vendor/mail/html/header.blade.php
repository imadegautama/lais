@props(['url'])
<tr>
<td class="header">
{{-- A PNG (Gmail and Outlook do not show SVG), carried inside the email as cid:lais-logo
     (AppServiceProvider::laisMail), so it shows without loading images from the web. --}}
<a href="{{ $url }}" style="display: inline-block;">
<img src="cid:lais-logo" width="83" height="32" alt="{{ config('app.name') }}" style="height: 32px; width: 83px; border: 0;">
</a>
</td>
</tr>
