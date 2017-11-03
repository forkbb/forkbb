        <form class="f-form" method="post" action="{!! $form['action'] !!}">
@if($form['hidden'])
@foreach($form['hidden'] as $key => $val)
          <input type="hidden" name="{{ $key }}" value="{{ $val }}">
@endforeach
@endif
@foreach($form['sets'] as $fieldset)
          <fieldset>
@if(isset($fieldset['legend']))
            <legend>{!! $fieldset['legend'] !!}</legend>
@endif
@foreach($fieldset['fields'] as $key => $cur)
@if(isset($cur['dl']))
            <dl class="f-field-{{ $cur['dl'] }}">
@else
            <dl>
@endif
@if(isset($cur['title']))
              <dt><label class="f-child1{!! empty($cur['required']) ? '' : ' f-req' !!}" for="id-{{ $key }}">{!! $cur['title'] !!}</label></dt>
@else
              <dt></dt>
@endif
              <dd>
@if($cur['type'] === 'textarea')
                <textarea{!! empty($cur['required']) ? '' : ' required' !!} class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">{{ $cur['value'] or '' }}</textarea>
@if(isset($cur['bb']))
                <ul class="f-child5">
@foreach($cur['bb'] as $val)
                  <li><span><a href="{!! $val[0] !!}">{!! $val[1] !!}</a> {!! $val[2] !!}</span></li>
@endforeach
                </ul>
@endif
@elseif($cur['type'] === 'text')
                <input{!! empty($cur['required']) ? '' : ' required' !!} class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="text" maxlength="{{ $cur['maxlength'] or '' }}" pattern="{{ $cur['pattern'] or '' }}" value="{{ $cur['value'] or '' }}">
@elseif($cur['type'] === 'checkbox')
                <label class="f-child2"><input type="checkbox" id="id-{{ $key }}" name="{{ $key }}" value="{{ $cur['value'] or '0' }}"{!! empty($cur['checked']) ? '' : ' checked' !!}>{!! $cur['label'] !!}</label>
@endif
@if(isset($cur['info']))
                <p class="f-child4">{!! $cur['info'] !!}</p>
@endif
              </dd>
            </dl>
@endforeach
          </fieldset>
@endforeach
          <p>
@foreach($form['btns'] as $key => $cur)
            <input class="f-btn" type="{{ $cur[0] }}" name="{{ $key }}" value="{{ $cur[1] }}" accesskey="{{ $cur[2] }}">
@endforeach
          </p>
        </form>
