@extends ('layouts/admin')
@isset ($p->logsInfo)
      <section id="fork-logsinfo" class="f-admin">
        <h2>{!! __('Logs') !!}</h2>
        <div>
          <fieldset>
            <p class="f-lgp f-lgli">
                <span class="f-lgname"><span>{!! __('Log name') !!}</span></span>
                <span class="f-llv f-llvem"><span>{!! __('Level emergency') !!}</span></span>
                <span class="f-llv f-llval"><span>{!! __('Level alert') !!}</span></span>
                <span class="f-llv f-llvcr"><span>{!! __('Level critical') !!}</span></span>
                <span class="f-llv f-llver"><span>{!! __('Level error') !!}</span></span>
                <span class="f-llv f-llvwa"><span>{!! __('Level warning') !!}</span></span>
                <span class="f-llv f-llvno"><span>{!! __('Level notice') !!}</span></span>
                <span class="f-llv f-llvin"><span>{!! __('Level info') !!}</span></span>
                <span class="f-llv f-llvde"><span>{!! __('Level debug') !!}</span></span>
                <span class="f-logbt"></span>
            </p>
            <ul>
    @foreach ($p->logsInfo as $hash => $cur)
              <li class="f-lgli">
                <span class="f-lgname f-lgsname">
                  <a class="f-lganame" href="{{ $cur['linkView'] }}" title="{!! __('View log %s', $cur['log_name']) !!}">{{ $cur['log_name'] }}</a>
                </span>
                <span class="f-llv @if ($cur['emergency']) f-llvem @endif" title="{!! __('Level emergency') !!}"><span>{{ num($cur['emergency']) }}</span></span>
                <span class="f-llv @if ($cur['alert']) f-llval @endif" title="{!! __('Level alert') !!}"><span>{{ num($cur['alert']) }}</span></span>
                <span class="f-llv @if ($cur['critical']) f-llvcr @endif" title="{!! __('Level critical') !!}"><span>{{ num($cur['critical']) }}</span></span>
                <span class="f-llv @if ($cur['error']) f-llver @endif" title="{!! __('Level error') !!}"><span>{{ num($cur['error']) }}</span></span>
                <span class="f-llv @if ($cur['warning']) f-llvwa @endif" title="{!! __('Level warning') !!}"><span>{{ num($cur['warning']) }}</span></span>
                <span class="f-llv @if ($cur['notice']) f-llvno @endif" title="{!! __('Level notice') !!}"><span>{{ num($cur['notice']) }}</span></span>
                <span class="f-llv @if ($cur['info']) f-llvin @endif" title="{!! __('Level info') !!}"><span>{{ num($cur['info']) }}</span></span>
                <span class="f-llv @if ($cur['debug']) f-llvde @endif" title="{!! __('Level debug') !!}"><span>{{ num($cur['debug']) }}</span></span>
                <span class="f-logbt">
                  <a class="f-btn f-lga f-lgadown" href="{{ $cur['linkDownload'] }}" title="{!! __('Download log') !!}"><span class="f-lgs">{!! __('Download log') !!}</span></a>
                  <a class="f-btn f-lga f-lgadel" href="{{ $cur['linkDelete'] }}" title="{!! __('Delete log') !!}"><span class="f-lgs">{!! __('Delete log') !!}</span></a>
                </span>
              </li>
    @endforeach
            </ul>
          </fieldset>
        </div>
      </section>
@endisset
@isset ($p->logData)
      <section id="fork-logview" class="f-admin">
        <h2>{!! __('Log %s', $p->logName) !!}</h2>
        <div>
          <fieldset>
            <ul id="id-lgdtlt">
    @foreach ($p->logData as $cur)
              <li class="f-lgli">
        @if ($cur['context'])
                <details class="f-lgdtl">
                  <summary class="f-lgsu">
        @endif
                    <span class="f-lgdt"><span class="f-lgdts">{{ $cur['datetime'] }}</span></span>
                    <span class="f-lglv @if ('emergency' === $cur['level_name']) f-llvem @elseif ('alert' === $cur['level_name']) f-llval @elseif ('critical' === $cur['level_name']) f-llvcr @elseif ('error' === $cur['level_name']) f-llver @elseif ('warning' === $cur['level_name']) f-llvwa @elseif ('notice' === $cur['level_name']) f-llvno @elseif ('info' === $cur['level_name']) f-llvin @elseif ('debug' === $cur['level_name']) f-llvde @endif"><span class="f-lglvs">{{ $cur['level_name'] }}</span></span>
                    <span class="f-lgmes"><span class="f-lgmess">{{ $cur['message'] }}</span></span>
        @if ($cur['context'])
                  </summary>
                  <pre class="f-lgpre">
{{ $cur['context'] }}
                  </pre>
                </details>
        @endif
              </li>
    @endforeach
            </ul>
          </fieldset>
        </div>
      </section>
@endisset
