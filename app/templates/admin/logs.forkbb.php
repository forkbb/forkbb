@extends ('layouts/admin')
@isset ($p->logsInfo)
      <section id="fork-logsinfo" class="f-admin">
        <h2>{!! __('Logs') !!}</h2>
        <div>
          <fieldset>
            <p></p>
            <ul>
    @foreach ($p->logsInfo as $hash => $cur)
              <li class="f-lgli">
                <span class="f-lgname" title="{!! __('Log name') !!}">{{ $cur['log_name'] }}</span>
                <span class="f-llv f-llvem" title="{!! __('Level emergency') !!}">{{ num($cur['emergency']) }}</span>
                <span class="f-llv f-llval" title="{!! __('Level alert') !!}">{{ num($cur['alert']) }}</span>
                <span class="f-llv f-llvcr" title="{!! __('Level critical') !!}">{{ num($cur['critical']) }}</span>
                <span class="f-llv f-llver" title="{!! __('Level error') !!}">{{ num($cur['error']) }}</span>
                <span class="f-llv f-llvwa" title="{!! __('Level warning') !!}">{{ num($cur['warning']) }}</span>
                <span class="f-llv f-llvno" title="{!! __('Level notice') !!}">{{ num($cur['notice']) }}</span>
                <span class="f-llv f-llvin" title="{!! __('Level info') !!}">{{ num($cur['info']) }}</span>
                <span class="f-llv f-llvde" title="{!! __('Level debug') !!}">{{ num($cur['debug']) }}</span>
                <span class="f-logbt">
                  <a class="f-btn f-lga f-lgaview" href="" title="{!! __('View log') !!}"><span class="f-lgs">{!! __('View log') !!}</span></a>
                  <a class="f-btn f-lga f-lgadown" href="" title="{!! __('Download log') !!}"><span class="f-lgs">{!! __('Download log') !!}</span></a>
                  <a class="f-btn f-lga f-lgadel" href="" title="{!! __('Delete log') !!}"><span class="f-lgs">{!! __('Delete log') !!}</span></a>
                </span>
              </li>
    @endforeach
            </ul>
          </fieldset>
        </div>
      </section>
@endisset
