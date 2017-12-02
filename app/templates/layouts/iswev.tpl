@if (isset($p->fIswev['i']))
    <section class="f-iswev f-info">
      <h2>Info message</h2>
      <ul>
  @foreach ($p->fIswev['i'] as $cur)
        <li class="f-icontent">{!! $cur !!}</li>
  @endforeach
      </ul>
    </section>
@endif
@if (isset($p->fIswev['s']))
    <section class="f-iswev f-success">
      <h2>Successful operation message</h2>
      <ul>
  @foreach ($p->fIswev['s'] as $cur)
        <li class="f-scontent">{!! $cur !!}</li>
  @endforeach
      </ul>
    </section>
@endif
@if (isset($p->fIswev['w']))
    <section class="f-iswev f-warning">
      <h2>Warning message</h2>
      <ul>
  @foreach ($p->fIswev['w'] as $cur)
        <li class="f-wcontent">{!! $cur !!}</li>
  @endforeach
      </ul>
    </section>
@endif
@if (isset($p->fIswev['e']))
    <section class="f-iswev f-error">
      <h2>Error message</h2>
      <ul>
  @foreach ($p->fIswev['e'] as $cur)
        <li class="f-econtent">{!! $cur !!}</li>
  @endforeach
      </ul>
    </section>
@endif
@if (isset($p->fIswev['v']))
    <section class="f-iswev f-validation">
      <h2>Validation message</h2>
      <ul>
  @foreach ($p->fIswev['v'] as $cur)
        <li class="f-vcontent">{!! $cur !!}</li>
  @endforeach
      </ul>
    </section>
@endif
