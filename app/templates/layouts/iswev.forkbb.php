      <aside class="f-iswev-wrap">
@if ($iswev['i'])
        <div class="f-iswev f-info">
          <p class="f-sim-header">Info message:</p>
          <ul>
    @foreach ($iswev['i'] as $cur)
            <li class="f-icontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev['s'])
        <div class="f-iswev f-success">
          <p class="f-sim-header">Successful operation message:</p>
          <ul>
    @foreach ($iswev['s'] as $cur)
            <li class="f-scontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev['w'])
        <div class="f-iswev f-warning">
          <p class="f-sim-header">Warning message:</p>
          <ul>
    @foreach ($iswev['w'] as $cur)
            <li class="f-wcontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev['e'])
        <div class="f-iswev f-error">
          <p class="f-sim-header">Error message:</p>
          <ul>
    @foreach ($iswev['e'] as $cur)
            <li class="f-econtent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev['v'])
        <div class="f-iswev f-validation">
          <p class="f-sim-header">Validation message:</p>
          <ul>
    @foreach ($iswev['v'] as $cur)
            <li class="f-vcontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
      </aside>
