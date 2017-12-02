        <form class="f-form" method="post" action="{!! $form['action'] !!}">
@if ($form['hidden'])
  @foreach ($form['hidden'] as $key => $val)
          <input type="hidden" name="{{ $key }}" value="{{ $val }}">
  @endforeach
@endif
@foreach ($form['sets'] as $fieldset)
          <fieldset>
  @if(isset ($fieldset['legend']))
            <legend>{!! $fieldset['legend'] !!}</legend>
  @endif
  @foreach ($fieldset['fields'] as $key => $cur)
            <dl @if (isset($cur['dl'])) class="f-field-{{ $cur['dl'] }}" @endif>
              <dt> @if (isset($cur['title']))<label class="f-child1 @if (isset($cur['required'])) f-req @endif" for="id-{{ $key }}">{!! $cur['title'] !!}</label> @endif</dt>
              <dd>
    @if ($cur['type'] === 'textarea')
                <textarea @if (isset($cur['required'])) required @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">{{ $cur['value'] or '' }}</textarea>
      @if (isset($cur['bb']))
                <ul class="f-child5">
        @foreach ($cur['bb'] as $val)
                  <li><span><a href="{!! $val[0] !!}">{!! $val[1] !!}</a> {!! $val[2] !!}</span></li>
        @endforeach
                </ul>
      @endif
    @elseif ($cur['type'] === 'text')
                <input @if (isset($cur['required'])) required @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="text" @if (! empty($cur['maxlength'])) maxlength="{{ $cur['maxlength'] }}" @endif @if (isset($cur['pattern'])) pattern="{{ $cur['pattern'] }}" @endif @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
    @elseif ($cur['type'] === 'checkbox')
                <label class="f-child2"><input type="checkbox" id="id-{{ $key }}" name="{{ $key }}" value="{{ $cur['value'] or '1' }}" @if (! empty($cur['checked'])) checked @endif>{!! $cur['label'] !!}</label>
    @endif
    @if (isset($cur['info']))
                <p class="f-child4">{!! $cur['info'] !!}</p>
    @endif
              </dd>
            </dl>
  @endforeach
          </fieldset>
@endforeach
          <p>
@foreach ($form['btns'] as $key => $cur)
            <input class="f-btn" type="{{ $cur[0] }}" name="{{ $key }}" value="{{ $cur[1] }}" accesskey="{{ $cur[2] }}">
@endforeach
          </p>
        </form>
