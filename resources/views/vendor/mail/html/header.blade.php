@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="display: inline-block; width: 28px; height: 28px; line-height: 28px; background: #0B0B0E; color: #FFFFFF; font-weight: 700; font-size: 16px; text-align: center; border-radius: 2px; vertical-align: middle;">H</span>
<span style="display: inline-block; margin-left: 8px; color: #0B0B0E; font-weight: 700; font-size: 18px; vertical-align: middle;">{{ $slot }}</span>
</a>
</td>
</tr>
