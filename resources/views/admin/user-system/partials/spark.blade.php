@php $max=max(1,collect($items ?? [])->max('value') ?: 1); @endphp
<div class="us-spark">@foreach($items ?? [] as $item)<span title="{{$item['label']}}: {{$item['value']}}"><i style="height:{{max(8,round(($item['value']/$max)*100))}}%"></i><small>{{$loop->index%4===0?$item['label']:''}}</small></span>@endforeach</div>
