@section ('pagination')
    @if ($p->pagination)
        <nav class="f-pages">
        @foreach ($p->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{!! __('Previous') !!}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{!! __('Next') !!}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/pm')
@if ($p->pagination)
      <div class="f-pm f-nav-links">
        <div class="f-nlinks-b f-nlbpm">
    @yield ('pagination')
        </div>
      </div>
@endif
      <section id="fork-forum" class="f-pm f-pmview @empty ($p->pmList) f-pm-empty @endempty">
        <h2>{!! __($p->title) !!}</h2>
@if (empty($p->pmList) && $iswev = ['i' => ['Info zero']])
        @include ('layouts/iswev')
@else
        <div class="f-ftlist">
          <ol class="f-table">
            <li hidden class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Dialogue') !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
    @foreach ($p->pmList as $id => $topic)
        @if (empty($topic->id) && $iswev = ['e' => [['Dialogue %s was not found in the database', $id]]])
            <li id="ptopic-{{ $id }}" class="f-row">
              @include ('layouts/iswev')
            </li>
        @else
            <li id="ptopic-{{ $topic->id }}" class="f-row @if ($topic->hasNew) f-fnew @endif @if ($topic->closed) f-fclosed @endif">
              <div class="f-cell f-cmain">
                <input hidden id="checkbox-{{ $topic->id }}" class="f-fch" type="checkbox" name="ids[{{ $topic->id }}]" value="{{ $topic->id }}" form="id-form-pmview">
                <label class="f-ficon" for="checkbox-{{ $topic->id }}" title="{{ __('Select') }}"></label>
                <div class="f-finfo">
                  <h3 class="f-finfo-h3">
            @if ($topic->closed)
                    <span class="f-tclosed" title="{{ __('Closed') }}"><small class="f-closedtxt">{!! __('Closed') !!}</small></span>
            @endif
                    <a class="f-ftname" href="{{ $topic->link }}">{{ $topic->name }}</a>
            @if ($topic->pagination)
                    <span class="f-tpages">
                @foreach ($topic->pagination as $cur)
                    @if ('space' === $cur[1])
                      <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
                    @else
                      <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
                    @endif
                @endforeach
                    </span>
            @endif
            @if ($topic->hasNew)
                    <small>(</small>
                    <span class="f-tnew"><a href="{{ $topic->linkNew }}" title="{{ __('New posts info') }}"><small class="f-newtxt">{!! __('New posts') !!}</small></a></span>
                    <small>)</small>
            @endif
                  </h3>
                  <p><!-- inline -->
                    <span class="f-cmposter">{!! __($topic->byOrFor) !!}</span>
<!-- endinline --></p>
                </div>
              </div>
              <div class="f-cell f-cstats">
                <span>{!! __(['%s Reply', $topic->num_replies, num($topic->num_replies)]) !!}</span>
              </div>
              <div class="f-cell f-clast">
                <span class="f-cltopic">{!! __(['Last post <a href="%1$s">%2$s</a>', $topic->linkLast, dt($topic->last_post)]) !!}</span>
                <span class="f-clposter">{!! __(['by %s', $topic->last_poster]) !!}</span>
              </div>
            </li>
        @endif
    @endforeach
          </ol>
        </div>
@endif
      </section>
@if ($p->pagination || $p->form)
      <div class="f-pm f-nav-links">
        <div class="f-nlinks-a f-nlbpm">
    @if ($form = $p->form)
          <div class="f-actions-links">
        @include ('layouts/form')
          </div>
    @endif
    @yield ('pagination')
        </div>
      </div>
@endif
