@extends ('layouts/admin')
      <section class="f-admin f-welcome">
        <h2>{!! __('Forum admin head') !!}</h2>
        <div>
          <fieldset>
            <p>{!! __('Welcome to admin') !!}</p>
            <ul>
              <li><span>{!! __('Welcome 1') !!}</span></li>
              <li><span>{!! __('Welcome 2') !!}</span></li>
              <li><span>{!! __('Welcome 3') !!}</span></li>
              <li><span>{!! __('Welcome 4') !!}</span></li>
              <li><span>{!! __('Welcome 5') !!}</span></li>
              <li><span>{!! __('Welcome 6') !!}</span></li>
              <li><span>{!! __('Welcome 7') !!}</span></li>
              <li><span>{!! __('Welcome 8') !!}</span></li>
              <li><span>{!! __('Welcome 9') !!}</span></li>
            </ul>
          </fieldset>
        </div>
      </section>
      <section class="f-admin">
        <h2>{!! __('About head') !!}</h2>
        <div class="f-fdiv">
          <fieldset>
            <dl>
              <dt>{!! __('ForkBB version label') !!}</dt>
              <dd>{!! __('ForkBB version data', $p->revision) !!}</dd>
            </dl>
            <dl>
              <dt>{!! __('Server statistics label') !!}</dt>
              <dd><a href="{{ $p->linkStat }}">{!! __('View server statistics') !!}</a></dd>
            </dl>
            <dl>
              <dt>{!! __('Support label') !!}</dt>
              <dd><a href="https://github.com/forkbb/forkbb">GitHub</a></dd>
            </dl>
          </fieldset>
        </div>
      </section>
