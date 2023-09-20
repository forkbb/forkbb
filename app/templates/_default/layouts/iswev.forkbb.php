      <aside class="f-iswev-wrap">
@if ($iswev[FORK_MESS_INFO])
        <div class="f-iswev f-info">
          <p class="f-sim-header">Info message:</p>
          <ul>
    @foreach ($iswev[FORK_MESS_INFO] as $cur)
            <li class="f-icontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev[FORK_MESS_SUCC])
        <div class="f-iswev f-success">
          <p class="f-sim-header">Successful operation message:</p>
          <ul>
    @foreach ($iswev[FORK_MESS_SUCC] as $cur)
            <li class="f-scontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev[FORK_MESS_WARN])
        <div class="f-iswev f-warning">
          <p class="f-sim-header">Warning message:</p>
          <ul>
    @foreach ($iswev[FORK_MESS_WARN] as $cur)
            <li class="f-wcontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev[FORK_MESS_ERR])
        <div class="f-iswev f-error">
          <p class="f-sim-header">Error message:</p>
          <ul>
    @foreach ($iswev[FORK_MESS_ERR] as $cur)
            <li class="f-econtent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
@if ($iswev[FORK_MESS_VLD])
        <div class="f-iswev f-validation">
          <p class="f-sim-header">Validation message:</p>
          <ul>
    @foreach ($iswev[FORK_MESS_VLD] as $cur)
            <li class="f-vcontent">{!! __($cur) !!}</li>
    @endforeach
          </ul>
        </div>
@endif
      </aside>
