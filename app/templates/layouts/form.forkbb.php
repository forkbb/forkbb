@if ($form['action'])
        <form @if ($form['id']) id="{{ $form['id'] }}" @endif class="f-form" method="post" action="{{ $form['action'] }}" @if ($form['enctype']) enctype="{{ $form['enctype'] }}" @endif>
@endif
@foreach ($form['sets'] as $setKey => $setVal)
    @if ($setVal['inform'])
        @foreach ($setVal['inform'] as $key => $cur)
          <p class="f-finform">{!! $cur['html'] or __($cur['message']) !!}</p>
        @endforeach
    @elseif (isset($setVal['fields']))
          <fieldset id="id-fs-{{ $setKey }}" @if ($setVal['class']) class="f-fs-{{ \implode(' f-fs-', $setVal['class']) }}" @endif>
        @if ($setVal['legend'])
            <legend class="f-fleg">{!! __($setVal['legend']) !!}</legend>
        @endif
        @foreach ($setVal['fields'] as $key => $cur)
            @switch ($cur['type'])
                @case ('info')
            <p id="id-{{ $cur['id'] or $setKey.$key }}" class="f-yinfo">{!! __($cur['value']) !!}</p>
                    @break
                @case ('wrap')
            <div id="id-{{ $cur['id'] or $setKey.$key }}" @if ($cur['class']) class="f-wrap-{{ \implode(' f-wrap-', $cur['class']) }}" @endif>
                    @break
                @case('endwrap')
            </div>
                    @break
                @default
            <dl id="id-dl-{{ $cur['id'] or $key }}" @if ($cur['class']) class="f-field-{{ \implode(' f-field-', $cur['class']) }}" @endif>
              <dt>
                    @if ($cur['caption'])
                <label class="f-ycaption @if ($cur['required']) f-req @endif" @if (false === \strpos('.radio.yield.str.btn.link.', ".{$cur['type']}.")) for="id-{{ $key }}" @endif>{!! __($cur['caption']) !!}</label>
                    @endif
              </dt>
              <dd>
                    @switch ($cur['type'])
                        @case ('text')
                        @case ('email')
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif @if ('email' === $cur['type']) autocapitalize="off" @endif class="f-ctrl f-ytxt" id="id-{{ $key }}" name="{{ $key }}" type="text" @if ($cur['maxlength']) maxlength="{{ $cur['maxlength'] }}" @endif @if ($cur['pattern']) pattern="{{ $cur['pattern'] }}" @endif @isset ($cur['value']) value="{{ $cur['value'] }}" @endisset>
                            @break
                        @case ('textarea')
                <textarea @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl f-ytxtarea" id="id-{{ $key }}" name="{{ $key }}" @if ($cur['data']) @foreach ($cur['data'] as $k => $v) data-{{ $k }}="{{ $v }}" @endforeach @endif>{{ $cur['value'] or '' }}</textarea>
                            @break
                        @case ('select')
                <select @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif @if ($cur['size']) size="{{ $cur['size'] }}" multiple @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}[]">
                            @if (!($count = null) && \is_array(\reset($cur['options'])) && 1 === \count(\reset($cur['options'])) && ($count = 0)) @endif
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
                            @break
                        @case ('number')
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="number" min="{{ $cur['min'] }}" max="{{ $cur['max'] }}" @isset ($cur['value']) value="{{ $cur['value'] }}" @endisset>
                            @break
                        @case ('checkbox')
                <label class="f-flblch"><input @if ($cur['autofocus']) autofocus @endif @if ($cur['disabled']) disabled @endif type="checkbox" class="f-ychk" id="id-{{ $key }}" name="{{ $key }}" value="{{ $cur['value'] or '1' }}" @if ($cur['checked']) checked @endif>@isset ($cur['label']){!! __($cur['label']) !!}@endif</label>
                            @break
                        @case ('radio')
                            @foreach ($cur['values'] as $v => $n)
                <label class="f-flblr"><input @if ($cur['autofocus']) autofocus @endif @if ($cur['disabled']) disabled @endif type="radio" class="f-yradio" id="id-{{ $key }}-{{ $v }}" name="{{ $key }}" value="{{ $v }}" @if ($v == $cur['value']) checked @endif>{{ $n }}</label>
                            @endforeach
                            @break
                        @case ('password')
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="password" @if ($cur['maxlength']) maxlength="{{ $cur['maxlength'] }}" @endif @if ($cur['pattern']) pattern="{{ $cur['pattern'] }}" @endif @isset ($cur['value']) value="{{ $cur['value'] }}" @endisset>
                            @break
                        @case ('btn' === $cur['type'])
                <a class="f-btn f-ybtn @if ($cur['disabled']) f-disabled @endif" href="{{ $cur['link'] or '' }}" title="{{ $cur['title'] or '' }}" @if ($cur['disabled']) tabindex="-1" @endif>{{ $cur['value'] }}</a>
                            @break
                        @case ('str')
                <p class="f-str" id="id-{{ $key }}"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</p>
                            @break
                        @case ('link')
                <a class="f-link" id="id-{{ $key }}" href="{{ $cur['href'] or '' }}" @isset ($cur['rel']) rel="{{ $cur['rel'] }}" @endisset title="{{ $cur['title'] or '' }}">{{ $cur['value'] or '' }}</a>
                            @break
                        @case ('yield')
                {!! $this->block($cur['value']) !!}
                            @break
                        @case ('file')
                <input @if ($cur['required']) required @endif @if ($cur['disabled']) disabled @endif @if ($cur['autofocus']) autofocus @endif class="f-ctrl" id="id-{{ $key }}" name="{{ $key }}" type="file" @if ($cur['accept']) accept="{{ $cur['accept'] }}" @endif>
                            @break
                    @endswitch
                    @if ($cur['help'])
                <p class="f-yhint">{!! __($cur['help']) !!}</p>
                    @endif
              </dd>
            </dl>
                    @break
            @endswitch
        @endforeach
          </fieldset>
    @endif
@endforeach
@if ($form['action'])
    @if ($form['hidden'])
        @foreach ($form['hidden'] as $key => $val)
            @if (\is_array($val))
                @foreach ($val as $k => $v)
          <input type="hidden" name="{{ $key }}[{{ $k }}]" value="{{ $v }}">
                @endforeach
            @else
          <input type="hidden" name="{{ $key }}" value="{{ $val }}">
            @endif
        @endforeach
    @endif
          <p class="f-btns">
    @foreach ($form['btns'] as $key => $cur)
        @if ('submit' === $cur['type'])
            <button class="f-btn f-fbtn @if($cur['class']) {{ \implode(' ', $cur['class']) }} @endif" name="{{ $key }}" value="{{ $cur['value'] }}" @isset ($cur['accesskey']) accesskey="{{ $cur['accesskey'] }}" @endisset title="{{ $cur['value'] }}"><span>{{ $cur['value'] }}</span></button>
        @elseif ('btn'=== $cur['type'])
            <a class="f-btn f-fbtn @if($cur['class']) {{ \implode(' ', $cur['class']) }} @endif" data-name="{{ $key }}" href="{{ $cur['link'] }}" @isset ($cur['accesskey']) accesskey="{{ $cur['accesskey'] }}" @endisset title="{{ $cur['value'] }}"><span>{{ $cur['value'] }}</span></a>
        @endif
    @endforeach
          </p>
        </form>
@endif
