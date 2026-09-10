@php $max=max(1,collect($items ?? [])->max('value') ?: 1); @endphp
<div class="us-bars">@forelse($items ?? [] as $item)<div><span>{{\Illuminate\Support\Str::limit((string)$item['label'],26)}}</span><i><b style="width:{{round(($item['value']/$max)*100)}}%"></b></i><em>{{$item['value']}}</em></div>@empty<p class="us-empty">No data yet.</p>@endforelse</div>
