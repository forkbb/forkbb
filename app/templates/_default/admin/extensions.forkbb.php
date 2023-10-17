@extends ('layouts/admin')
@isset ($p->extensions)
      <section id="fork-extsinfo" class="f-admin">
        <h2>{!! __('Extensions') !!}</h2>
        <div>
          <fieldset>
            <ol>
    @foreach ($p->extensions as $ext)
              <li class="f-extli f-ext-status{{ $ext->status }}">
                <details class="f-extdtl">
                  <summary class="f-extsu">
                    <span>{{ $ext->dispalyName }}</span>
                    -
                    <span>{{ $ext->version }}</span>
                    <span>/
        @switch ($ext->status)
            @case ($ext::NOT_INSTALLED)
                    {!! __('Not installed') !!}
                @break
            @case ($ext::DISABLED)
                    {!! __('Disabled') !!}
                @break
            @case ($ext::DISABLED_DOWN)
            @case ($ext::DISABLED_UP)
                    {!! __('Disabled, package changed') !!}
                @break
            @case ($ext::ENABLED)
                    {!! __('Enabled') !!}
                @break
            @case ($ext::ENABLED_DOWN)
            @case ($ext::ENABLED_UP)
                    {!! __('Enabled, package changed') !!}
                @break
            @case ($ext::CRASH)
                    {!! __('Crash') !!}
                @break
        @endswitch
                    /<span>
                  </summary>
                  <div class="f-extdata f-fdiv">
                    <fieldset class="f-extfs-details">
                      <legend class="f-fleg">{!! __('Details') !!}</legend>
                      <dl>
                        <dt>{!! __('Name') !!}</dt>
                        <dd>{{ $ext->name }}</dd>
                      </dl>
                      <dl>
                        <dt>{!! __('Description') !!}</dt>
                        <dd>{{ $ext->description }}</dd>
                      </dl>
        @if ($ext->time)
                      <dl>
                        <dt>{!! __('Release date') !!}</dt>
                        <dd>{{ $ext->time }}</dd>
                      </dl>
        @endif
        @if ($ext->homepage)
                      <dl>
                        <dt>{!! __('Homepage') !!}</dt>
                        <dd><a href="{{ url($ext->homepage) }}">{{ $ext->homepage }}</a></dd>
                      </dl>
        @endif
        @if ($ext->license)
                      <dl>
                        <dt>{!! __('Licence') !!}</dt>
                        <dd>{{ $ext->license }}</dd>
                      </dl>
        @endif
                    </fieldset>
                    <fieldset class="f-extfs-requirements">
                      <legend class="f-fleg">{!! __('Requirements') !!}</legend>
        @foreach ($ext->requirements as $k => $v)
                      <dl>
                        <dt>{!! __($k) !!}</dt>
                        <dd>{{ $v }}</dd>
                      </dl>
        @endforeach
                    </fieldset>
                    <fieldset class="f-extfs-authors">
                      <legend class="f-fleg">{!! __('Authors') !!}</legend>
        @foreach ($ext->authors as $author)
                      <dl>
                        <dd class="f-extdd-author">
                          <span>{{ $author['name'] }}</span>
            @if (! empty($author['email']) || ! empty($author['homepage']))
                          (
                @if ($author['email'])
                          <a href="{{ url('mailto:'.$author['email']) }}">{{ $author['email'] }}</a>
                @endif
                @if ($author['homepage'])
                  @if ($author['email'])
                          |
                  @endif
                          <a href="{{ url($author['homepage']) }}">{{ $author['homepage'] }}</a>
                @endif
                          )
            @endif
            @if ($author['role'])
                          [ {{ $author['role'] }} ]
            @endif
                        </dd>
                      </dl>
        @endforeach
                    </fieldset>
                  </div>
                </details>
              </li>
    @endforeach
            </ol>
          </fieldset>
        </div>
      </section>
@endisset
