        <form class="f-form" method="post" action="{!! $form['action'] !!}">
@if ($form['hidden'])
  @foreach ($form['hidden'] as $key => $val)
          <input type="hidden" name="{{ $key }}" value="{{ $val }}">
  @endforeach
@endif
@foreach ($form['sets'] as $set)
  @if (isset($set['info']))
    @foreach ($set['info'] as $key => $cur)
      @if (empty($cur['html']))
          <p class="f-finfo">{{ $cur['value'] }}</p>
      @else
          <p class="f-finfo">{!! $cur['value'] !!}</p>
      @endif
    @endforeach
  @elseif (isset($set['fields']))
          <fieldset>
    @if (isset($set['legend']))
            <legend>{!! $set['legend'] !!}</legend>
    @endif
    @foreach ($set['fields'] as $key => $cur)
            <dl @if (isset($cur['dl'])) class="f-field-{!! implode(' f-field-', (array) $cur['dl']) !!}" @endif>
              <dt> @if (isset($cur['title']))<label class="f-child1 @if (isset($cur['required'])) f-req @endif" @if (is_string($key)) for="id-{{ $key }}" @endif>{!! $cur['title'] !!}</label> @endif</dt>
              <dd>
      @if ('text' === $cur['type'])
                <input @if (isset($cur['required'])) required @endif @if (! empty($cur['disabled'])) disabled @endif @if (isset($cur['autofocus'])) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="text" @if (! empty($cur['maxlength'])) maxlength="{{ $cur['maxlength'] }}" @endif @if (isset($cur['pattern'])) pattern="{{ $cur['pattern'] }}" @endif @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
      @elseif ('textarea' === $cur['type'])
                <textarea @if (isset($cur['required'])) required @endif @if (! empty($cur['disabled'])) disabled @endif @if (isset($cur['autofocus'])) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">{{ $cur['value'] or '' }}</textarea>
        @if (isset($cur['bb']))
                <ul class="f-child5">
          @foreach ($cur['bb'] as $val)
                  <li><span><a href="{!! $val[0] !!}">{!! $val[1] !!}</a> {!! $val[2] !!}</span></li>
          @endforeach
                </ul>
        @endif
      @elseif ('select' === $cur['type'])
                <select @if (isset($cur['required'])) required @endif @if (! empty($cur['disabled'])) disabled @endif @if (isset($cur['autofocus'])) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">
        @foreach ($cur['options'] as $v => $option)
          @if (is_array($option))
                  <option value="{{ $option[0] }}" @if ($option[0] == $cur['value']) selected @endif @if (isset($option[2])) disabled @endif>{{ $option[1] }}</option>
          @else
                  <option value="{{ $v }}" @if ($v == $cur['value']) selected @endif>{{ $option }}</option>
          @endif
        @endforeach
                </select>
      @elseif ('number' === $cur['type'])
                <input @if (isset($cur['required'])) required @endif @if (! empty($cur['disabled'])) disabled @endif @if (isset($cur['autofocus'])) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="number" min="{{ $cur['min'] }}" max="{{ $cur['max'] }}" @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
      @elseif ('checkbox' === $cur['type'])
                <label class="f-child2"><input @if (isset($cur['autofocus'])) autofocus @endif @if (! empty($cur['disabled'])) disabled @endif type="checkbox" id="id-{{ $key }}" name="{{ $key }}" value="{{ $cur['value'] or '1' }}" @if (! empty($cur['checked'])) checked @endif>{!! $cur['label'] !!}</label>
      @elseif ('radio' === $cur['type'])
        @foreach ($cur['values'] as $v => $n)
                <label class="f-label"><input @if (isset($cur['autofocus'])) autofocus @endif @if (! empty($cur['disabled'])) disabled @endif type="radio" id="id-{{ $key }}-{{ $v }}" name="{{ $key }}" value="{{ $v }}" @if ($v == $cur['value']) checked @endif>{{ $n }}</label>
        @endforeach
      @elseif ('password' === $cur['type'])
                <input @if (isset($cur['required'])) required @endif @if (! empty($cur['disabled'])) disabled @endif @if (isset($cur['autofocus'])) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="password" @if (! empty($cur['maxlength'])) maxlength="{{ $cur['maxlength'] }}" @endif @if (isset($cur['pattern'])) pattern="{{ $cur['pattern'] }}" @endif @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
      @elseif ('btn' === $cur['type'])
                <a class="f-btn @if (! empty($cur['disabled'])) f-disabled @endif" href="{!! $cur['link'] !!}" @if (! empty($cur['disabled'])) tabindex="-1" @endif>{{ $cur['value'] }}</a>
      @endif
      @if (isset($cur['info']))
                <p class="f-child4">{!! $cur['info'] !!}</p>
      @endif
              </dd>
            </dl>
    @endforeach
          </fieldset>
  @endif
@endforeach
          <p class="f-btns">
@foreach ($form['btns'] as $key => $cur)
  @if ('submit' === $cur['type'])
            <input class="f-btn @if(isset($cur['class'])) {{ $cur['class'] }} @endif" type="{{ $cur['type'] }}" name="{{ $key }}" value="{{ $cur['value'] }}" @if (isset($cur['accesskey'])) accesskey="{{ $cur['accesskey'] }}" @endif>
  @elseif ('btn'=== $cur['type'])          
            <a class="f-btn @if(isset($cur['class'])) {{ $cur['class'] }} @endif" data-name="{{ $key }}" href="{!! $cur['link'] !!}" @if (isset($cur['accesskey'])) accesskey="{{ $cur['accesskey'] }}" @endif>{{ $cur['value'] }}</a>
  @endif
@endforeach
          </p>
        </form>
