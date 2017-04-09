@extends('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Add group subhead') !!}</h2>
        <div class="f-fdiv">
          <form class="f-form" method="post" action="{!! $formActionNew !!}">
            <input type="hidden" name="token" value="{!! $formTokenNew !!}">
            <dl>
              <dt>{!! __('New group label') !!}</dt>
              <dd>
                <select class="f-ctrl" id="id-basegroup" name="basegroup" tabindex="{!! ++$tabindex !!}">
@foreach($groupsNew as $cur)
@if ($cur[0] == $defaultGroup)
                  <option value="{!! $cur[0] !!}" selected>{{ $cur[1] }}</option>
@else
                  <option value="{!! $cur[0] !!}">{{ $cur[1] }}</option>
@endif
@endforeach
                </select>
                <span class="f-child4">{!! __('New group help') !!}</span>
              </dd>
            </dl>
            <div>
              <input class="f-btn" type="submit" name="submit" value="{!! __('Add') !!}" tabindex="{!! ++$tabindex !!}">
            </div>
          </form>
        </div>
      </section>
      <section class="f-admin">
        <h2>{!! __('Default group subhead') !!}</h2>
        <div class="f-fdiv">
          <form class="f-form" method="post" action="{!! $formActionDefault !!}">
            <input type="hidden" name="token" value="{!! $formTokenDefault !!}">
            <dl>
              <dt>{!! __('Default group label') !!}</dt>
              <dd>
                <select class="f-ctrl" id="id-defaultgroup" name="defaultgroup" tabindex="{!! ++$tabindex !!}">
@foreach($groupsDefault as $cur)
@if ($cur[0] == $defaultGroup)
                  <option value="{!! $cur[0] !!}" selected>{{ $cur[1] }}</option>
@else
                  <option value="{!! $cur[0] !!}">{{ $cur[1] }}</option>
@endif
@endforeach
                </select>
                <span class="f-child4">{!! __('Default group help') !!}</span>
              </dd>
            </dl>
            <div>
              <input class="f-btn" type="submit" name="submit" value="{!! __('Save') !!}" tabindex="{!! ++$tabindex !!}">
            </div>
          </form>
        </div>
      </section>
      <section class="f-admin">
        <h2>{!! __('Edit groups subhead') !!}</h2>
        <div>
          <p>{!! __('Edit groups info') !!}</p>
          <ol class="f-grlist">
@foreach($groupsList as $cur)
            <li>
              <a href="{!! $cur[0] !!}" tabindex="{!! ++$tabindex !!}">{{ $cur[1] }}</a>
@if($cur[2])
              <a class="f-btn" href="{!! $cur[2] !!}" tabindex="{!! ++$tabindex !!}">{!! __('Delete link') !!}</a>
@endif
            </li>
@endforeach
          </ol>
        </div>
      </section>
