<div class="settings-kpis" aria-label="Settings summary">
    @foreach($metrics as $metric)
        <article class="settings-kpi settings-kpi--{{$metric['tone']}}">
            <span class="settings-kpi-icon"><x-icon name="{{$metric['icon']}}" size="18" /></span>
            <span class="settings-kpi-copy"><small>{{$metric['label']}}</small><strong>{{$metric['value']}}</strong></span>
        </article>
    @endforeach
</div>
