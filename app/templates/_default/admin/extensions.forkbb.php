@extends ('layouts/admin')
@isset ($p->extensions)
      <section id="fork-extsinfo" class="f-admin">
        <h2>{!! __('Extensions') !!}</h2>
        <div>
          <fieldset>
            <ol>
    @foreach ($p->extensions as $ext)
              <li id="{{ $ext->id }}" class="f-extli f-ext-status{{ $ext->status }}">
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
                    <form class="f-form" method="post" action="{{ $p->actionLink }}">
                      <fieldset class="f-extfs-details">
                        <legend class="f-fleg">{!! __('Details') !!}</legend>
                        <dl>
                          <dt>{!! __('Name') !!}</dt>
                          <dd>{{ $ext->name }}</dd>
                        </dl>
                        <dl>
                          <dt>{!! __('Package version') !!}</dt>
                          <dd>{{ $ext->fileVersion }}</dd>
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
                      <fieldset calss="f-extfs-confirm">
                        <dl>
                          <dd>
                            <label class="f-flblch"><input name="confirm" class="f-ychk" type="checkbox" value="1">{!! __('Confirm action') !!}</label>
                          </dd>
                        </dl>
                      </fieldset>
                      <input type="hidden" name="name" value="{{ $ext->name }}">
                      <input type="hidden" name="token" value="{{ $p->formsToken }}">
                      <p class="f-btns">
        @if ($ext->canInstall)
                        <button class="f-btn f-fbtn" name="install" value="install" title="{{ __('Install_') }}"><span>{!! __('Install_') !!}</span></button>
        @endif
        @if ($ext->canUninstall)
                        <button class="f-btn f-fbtn" name="uninstall" value="uninstall" title="{{ __('Uninstall_') }}"><span>{!! __('Uninstall_') !!}</span></button>
        @endif
        @if ($ext->canUpdate)
                        <button class="f-btn f-fbtn" name="update" value="update" title="{{ __('Update_') }}"><span>{!! __('Update_') !!}</span></button>
        @endif
        @if ($ext->canDowndate)
                        <button class="f-btn f-fbtn" name="downdate" value="downdate" title="{{ __('Downdate_') }}"><span>{!! __('Downdate_') !!}</span></button>
        @endif
        @if ($ext->canEnable)
                        <button class="f-btn f-fbtn" name="enable" value="enable" title="{{ __('Enable_') }}"><span>{!! __('Enable_') !!}</span></button>
        @endif
        @if ($ext->canDisable)
                        <button class="f-btn f-fbtn" name="disable" value="disable" title="{{ __('Disable_') }}"><span>{!! __('Disable_') !!}</span></button>
        @endif
                      </p>
                    </form>
                  </div>
                </details>
              </li>
    @endforeach
            </ol>
          </fieldset>
        </div>
      </section>
@endisset
