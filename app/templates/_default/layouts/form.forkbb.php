        <!-- PRE start -->
@if ($form['action'])
        <form @if ($form['id']) id="{{ $form['id'] }}" @endif class="f-form" method="post" action="{{ $form['action'] }}" @if ($form['enctype']) enctype="{{ $form['enctype'] }}" @endif>
          <!-- PRE formStart -->
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
                <label class="f-ycaption @if ($cur['required']) f-req @endif" @if (false === \strpos('.radio.yield.str.btn.link.label.include.', ".{$cur['type']}.")) for="id-{{ $key }}" @endif>{!! __($cur['caption']) !!}</label>
                    @endif
              </dt>
              <dd>
                    @switch ($cur['type'])
                <!-- PRE switchStart -->
                        @case ('text')
                        @case ('email')
                        @case ('number')
                        @case ('password')
                        @case ('file')
                        @case ('datetime-local')
                <input id="id-{{ $key }}" name="{{ $key }}" class="f-ctrl" type="{{ $cur['type'] }}" @foreach ($cur as $k => $v) @if (\in_array($k, ['autofocus', 'disabled', 'multiple', 'readonly', 'required'], true) && ! empty($v)) {!! $k !!} @elseif (\in_array($k, ['accept', 'autocapitalize', 'autocomplete', 'max', 'maxlength', 'min', 'minlength', 'pattern', 'placeholder', 'step', 'title', 'value'], true)) {!! $k !!}="{{ $v }}" @endif @endforeach>
                            @break
                        @case ('textarea')
                <textarea id="id-{{ $key }}" name="{{ $key }}" class="f-ctrl f-ytxtarea" @foreach ($cur as $k => $v) @if (\in_array($k, ['autofocus', 'disabled', 'readonly', 'required'], true) && ! empty($v)) {!! $k !!} @elseif (\in_array($k, ['maxlength', 'placeholder', 'rows', 'title'], true)) {!! $k !!}="{{ $v }}" @elseif ('data' === $k) @foreach ($v as $kd => $vd) data-{{ $kd }}="{{ $vd }}" @endforeach @endif @endforeach>{{ $cur['value'] or '' }}</textarea>
                            @break
                        @case ('select')
                <select id="id-{{ $key }}" @if ($cur['multiple']) name="{{ $key }}[]" @else name="{{ $key }}" @endif class="f-ctrl" @foreach ($cur as $k => $v) @if (\in_array($k, ['autofocus', 'disabled', 'multiple', 'required'], true) && ! empty($v)) {!! $k !!} @elseif (\in_array($k, ['size'], true)) {!! $k !!}="{{ $v }}" @endif @endforeach>
                            @if (!($count = null) && \is_array(\reset($cur['options'])) && 1 === \count(\reset($cur['options'])) && ($count = 0)) @endif
                            @foreach ($cur['options'] as $v => $option)
                                @if (\is_array($option))
                                    @if (null !== $count && 1 === \count($option))
                                        @if (++$count > 1)
                </optgroup>
                                        @endif
                <optgroup label="{{ $option[0] }}">
                                    @else
                  <option value="{{ $option[0] }}" @if ($cur['cprefix']) class="{{ $cur['cprefix'] . $option[0] }}" @endif @if ((\is_array($cur['value']) && \in_array($option[0], $cur['value'])) || $option[0] == $cur['value']) selected @endif @if ($option[2]) disabled @endif>{{ $option[1] }}</option>
                                    @endif
                                @else
                  <option value="{{ $v }}" @if ($cur['cprefix']) class="{{ $cur['cprefix'] . $v }}" @endif @if ((\is_array($cur['value']) && \in_array($v, $cur['value'])) || $v == $cur['value']) selected @endif>{{ $option }}</option>
                                @endif
                            @endforeach
                            @if (null !== $count)
                </optgroup>
                            @endif
                </select>
                            @break
                        @case ('checkbox')
                <label class="f-flblch"><input id="id-{{ $key }}" name="{{ $key }}" class="f-ychk" type="checkbox" @foreach ($cur as $k => $v) @if (\in_array($k, ['autofocus', 'disabled', 'checked'], true) && ! empty($v)) {!! $k !!} @endif @endforeach value="{{ $cur['value'] or '1' }}"> @isset ($cur['label']){!! __($cur['label']) !!} @endisset</label>
                            @break
                        @case ('radio')
                            @foreach ($cur['values'] as $v => $n)
                <label class="f-flblr"><input id="id-{{ $key }}-{{ $v }}" name="{{ $key }}" class="f-yradio" type="radio" @if ($cur['autofocus']) autofocus @endif @if ($cur['disabled']) disabled @endif value="{{ $v }}" @if ($v == $cur['value']) checked @endif>{{ $n }}</label>
                            @endforeach
                            @break
                        @case ('btn')
                <a id="id-{{ $key }}" class="f-btn f-ybtn @if ($cur['disabled']) f-disabled @endif" href="{{ $cur['href'] }}" title="{{ $cur['title'] or $cur['value'] }}" @if ($cur['disabled']) tabindex="-1" @endif>{{ $cur['value'] }}</a>
                            @break
                        @case ('link')
                <a id="id-{{ $key }}" class="f-link" href="{{ $cur['href'] }}" @if ($cur['rel']) rel="{{ $cur['rel'] }}" @endif title="{{ $cur['title'] or $cur['value'] }}">{{ $cur['value'] }}</a>
                            @break
                        @case ('str')
                <p id="id-{{ $key }}" class="f-str"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</p>
                            @break
                        @case ('label')
                <label id="id-{{ $key }}" class="f-label" for="id-{{ $cur['for'] }}"> @if ($cur['html']){!! $cur['value'] !!} @else{{ $cur['value'] }} @endif</label>
                            @break
                        @case ('yield')
                @yield($cur['value'])
                            @break
                        @case ('include')
                @include($cur['include'])
                            @break
                    @endswitch
                    @if ($cur['help'])
                <p class="f-yhint">{!! __($cur['help']) !!}</p>
                    @endif
              </dd>
            </dl>
                    @break
                <!-- PRE switchEnd -->
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
            <!-- PRE btnsForeachStart -->
        @if ('submit' === $cur['type'])
            <button class="f-btn f-fbtn @if($cur['class']) {{ \implode(' ', $cur['class']) }} @endif" name="{{ $key }}" value="{{ $cur['value'] }}" @isset ($cur['accesskey']) accesskey="{{ $cur['accesskey'] }}" @endisset title="{{ $cur['value'] }}" @if ($cur['disabled']) disabled @endif><span>{{ $cur['value'] }}</span></button>
        @elseif ('btn'=== $cur['type'])
            <a class="f-btn f-fbtn @if($cur['class']) {{ \implode(' ', $cur['class']) }} @endif" data-name="{{ $key }}" href="{{ $cur['href'] }}" @isset ($cur['accesskey']) accesskey="{{ $cur['accesskey'] }}" @endisset title="{{ $cur['value'] }}"><span>{{ $cur['value'] }}</span></a>
        @endif
            <!-- PRE btnsForeachEnd -->
    @endforeach
          </p>
          <!-- PRE formEnd -->
        </form>
@endif
        <!-- PRE end -->
