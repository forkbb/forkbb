@if ($form['action'])
        <form @if ($form['id']) id="{!! $form['id'] !!}" @endif class="f-form" method="post" action="{!! $form['action'] !!}" @if ($form['enctype']) enctype="{{ $form['enctype'] }}" @endif>
    @if ($form['hidden'])
        @foreach ($form['hidden'] as $key => $val)
          <input type="hidden" name="{{ $key }}" value="{{ $val }}">
        @endforeach
    @endif
@endif
@foreach ($form['sets'] as $setKey => $setVal)
    @if ($setVal['info'])
        @foreach ($setVal['info'] as $key => $cur)
          <p class="f-finfo"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</p>
        @endforeach
    @elseif ($setVal['fields'])
          <fieldset id="id-fs-{{ $setKey }}" @if ($setVal['class']) class="f-fs-{!! \implode(' f-fs-', (array) $setVal['class']) !!}" @endif>
        @if ($setVal['legend'])
            <legend>{!! $setVal['legend'] !!}</legend>
        @endif
        @foreach ($setVal['fields'] as $key => $cur)
            @if ('info' === $cur['type'])
            <p id="id-{{ $cur['id'] or $key }}" class="f-child6"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</p>
            @elseif ('wrap' === $cur['type'])
            <div id="id-{{ $cur['id'] or $key }}" @if ($cur['class']) class="f-wrap-{!! \implode(' f-wrap-', (array) $cur['class']) !!}" @endif>
            @elseif ('endwrap' === $cur['type'])
            </div>
            @else
            <dl id="id-dl-{{ $cur['id'] or $key }}" @if ($cur['class']) class="f-field-{!! \implode(' f-field-', (array) $cur['class']) !!}" @endif>
              <dt> @if ($cur['caption'])<label class="f-child1 @if ($cur['required']) f-req @endif" @if (false === \strpos('.radio.yield.str.btn.link.', ".{$cur['type']}.")) for="id-{{ $key }}" @endif>{!! $cur['caption'] !!}</label> @endif</dt>
              <dd>
                @if ('text' === $cur['type'])
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="text" @if ($cur['maxlength']) maxlength="{{ $cur['maxlength'] }}" @endif @if ($cur['pattern']) pattern="{{ $cur['pattern'] }}" @endif @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
                @elseif ('textarea' === $cur['type'])
                <textarea @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">{{ $cur['value'] or '' }}</textarea>
                    @if ($cur['bb'])
                <ul class="f-child5">
                        @foreach ($cur['bb'] as $val)
                  <li><span><a href="{!! $val[0] !!}">{!! $val[1] !!}</a> {!! $val[2] !!}</span></li>
                        @endforeach
                </ul>
                    @endif
                @elseif ('select' === $cur['type'])
                <select @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}">
                    @if (null === ($count = null) && \is_array(\reset($cur['options'])) && 1 === \count(\reset($cur['options'])) && $count = 0) @endif
                    @foreach ($cur['options'] as $v => $option)
                        @if (\is_array($option))
                            @if (null !== $count && 1 === \count($option))
                                @if (++$count > 1)
                </optgroup>
                                @endif
                <optgroup label="{{ $option[0] }}">
                            @else
                  <option value="{{ $option[0] }}" @if ($option[0] == $cur['value']) selected @endif @if ($option[2]) disabled @endif>{{ $option[1] }}</option>
                            @endif
                        @else
                  <option value="{{ $v }}" @if ($v == $cur['value']) selected @endif>{{ $option }}</option>
                        @endif
                    @endforeach
                    @if (null !== $count)
                </optgroup>
                    @endif
                </select>
                @elseif ('multiselect' === $cur['type'])
                <select @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif @if ($cur['size']) size="{{ $cur['size'] }}" @endif multiple class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}[]">
                    @if (null === ($count = null) && \is_array(\reset($cur['options'])) && 1 === \count(\reset($cur['options'])) && $count = 0) @endif
                    @foreach ($cur['options'] as $v => $option)
                        @if (\is_array($option))
                            @if (null !== $count && 1 === \count($option))
                                @if (++$count > 1)
                </optgroup>
                                @endif
                <optgroup label="{{ $option[0] }}">
                            @else
                  <option value="{{ $option[0] }}" @if ((\is_array($cur['value']) && \in_array($option[0], $cur['value'])) || $option[0] == $cur['value']) selected @endif @if ($option[2]) disabled @endif>{{ $option[1] }}</option>
                            @endif
                        @else
                  <option value="{{ $v }}" @if ((\is_array($cur['value']) && \in_array($v, $cur['value'])) || $v == $cur['value']) selected @endif>{{ $option }}</option>
                        @endif
                    @endforeach
                    @if (null !== $count)
                </optgroup>
                    @endif
                </select>
                @elseif ('number' === $cur['type'])
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="number" min="{{ $cur['min'] }}" max="{{ $cur['max'] }}" @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
                @elseif ('checkbox' === $cur['type'])
                <label class="f-child2"><input @if ($cur['autofocus']) autofocus @endif @if ($cur['disabled']) disabled @endif type="checkbox" id="id-{{ $key }}" name="{{ $key }}" value="{{ $cur['value'] or '1' }}" @if ($cur['checked']) checked @endif>{!! $cur['label'] or '' !!}</label>
                @elseif ('radio' === $cur['type'])
                    @foreach ($cur['values'] as $v => $n)
                <label class="f-label"><input @if ($cur['autofocus']) autofocus @endif @if ($cur['disabled']) disabled @endif type="radio" id="id-{{ $key }}-{{ $v }}" name="{{ $key }}" value="{{ $v }}" @if ($v == $cur['value']) checked @endif>{{ $n }}</label>
                    @endforeach
                @elseif ('password' === $cur['type'])
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="password" @if ($cur['maxlength']) maxlength="{{ $cur['maxlength'] }}" @endif @if ($cur['pattern']) pattern="{{ $cur['pattern'] }}" @endif @if (isset($cur['value'])) value="{{ $cur['value'] }}" @endif>
                @elseif ('btn' === $cur['type'])
                <a class="f-btn @if ($cur['disabled']) f-disabled @endif" href="{!! $cur['link'] !!}" title="{{ $cur['title'] or '' }}" @if ($cur['disabled']) tabindex="-1" @endif>{{ $cur['value'] }}</a>
                @elseif ('str' === $cur['type'])
                <p class="f-str" id="id-{{ $key }}"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</p>
                @elseif ('link' === $cur['type'])
                <a class="f-link" id="id-{{ $key }}" href="{{ $cur['href'] or '' }}" title="{{ $cur['title'] or '' }}">{{ $cur['value'] or '' }}</a>
                @elseif ('yield' === $cur['type'])
                {!! $this->block($cur['value']) !!}
                @elseif ('file' === $cur['type'])
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="file">
                @endif
                @if ($cur['info'])
                <p class="f-child4">{!! $cur['info'] !!}</p>
                @endif
              </dd>
            </dl>
            @endif
        @endforeach
          </fieldset>
    @endif
@endforeach
@if ($form['action'])
          <p class="f-btns">
    @foreach ($form['btns'] as $key => $cur)
        @if ('submit' === $cur['type'])
            <input class="f-btn @if($cur['class']) {{ $cur['class'] }} @endif" type="{{ $cur['type'] }}" name="{{ $key }}" value="{{ $cur['value'] }}" @if (isset($cur['accesskey'])) accesskey="{{ $cur['accesskey'] }}" @endif>
        @elseif ('btn'=== $cur['type'])
            <a class="f-btn @if($cur['class']) {{ $cur['class'] }} @endif" data-name="{{ $key }}" href="{!! $cur['link'] !!}" @if (isset($cur['accesskey'])) accesskey="{{ $cur['accesskey'] }}" @endif>{{ $cur['value'] }}</a>
        @endif
    @endforeach
          </p>
        </form>
@endif
