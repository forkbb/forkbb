@extends('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Server statistics head') !!}</h2>
        <div>
          <dl>
            <dt>{!! __('Server load label') !!}</dt>
            <dd>{!! __('Server load data', $p->serverLoad, $p->numOnline) !!}</dd>
@if($p->isAdmin)
            <dt>{!! __('Environment label') !!}</dt>
            <dd>
              {!! __('Environment data OS', PHP_OS) !!}<br>
              {!! __('Environment data version', phpversion(), '<a href="' . $p->linkInfo . '">'.__('Show info').'</a>') !!}<br>
              {!! __('Environment data acc', $p->accelerator) !!}
            </dd>
            <dt>{!! __('Database label') !!}</dt>
            <dd>
              {{ $p->dbVersion }}
@if($p->tRecords && $p->tSize)
              <br>{!! __('Database data rows', $p->tRecords) !!}
              <br>{!! __('Database data size', $p->tSize) !!}
@endif
@if($p->tOther)
              <br><br>{!! __('Other')!!}
@foreach($p->tOther as $key => $value)
              <br>{{ $key }} = {{ $value }}
@endforeach
@endif
            </dd>
@endif
          </dl>
        </div>
      </section>
