@extends('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Server statistics head') !!}</h2>
        <div>
          <dl>
            <dt>{!! __('Server load label') !!}</dt>
            <dd>{!! __('Server load data', $serverLoad, $numOnline) !!}</dd>
@if($isAdmin)
            <dt>{!! __('Environment label') !!}</dt>
            <dd>
              {!! __('Environment data OS', PHP_OS) !!}<br>
              {!! __('Environment data version', phpversion(), '<a href="' . $linkInfo . '">'.__('Show info').'</a>') !!}<br>
              {!! __('Environment data acc', $accelerator) !!}
            </dd>
            <dt>{!! __('Database label') !!}</dt>
            <dd>
              {!! $dbVersion !!}
@if($tRecords && $tSize)
              <br>{!! __('Database data rows', $tRecords) !!}
              <br>{!! __('Database data size', $tSize) !!}
@endif
            </dd>
@endif
          </dl>
        </div>
      </section>
