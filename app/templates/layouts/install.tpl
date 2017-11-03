<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{!! __('ForkBB Installation') !!}</title>
@foreach($p->pageHeads as $cur)
  <{!! $cur !!}>
@endforeach
</head>
<body>
  <div class="f-wrap">
    <header class="f-header">
      <div class="f-title">
        <h1>{!! __('ForkBB Installation') !!}</h1>
        <p class="f-description">{!! __('Welcome') !!}</p>
      </div>
    </header>
@if($p->fIswev)
@include('layouts/iswev')
@endif
@if(is_array($p->installLangs))
    <section class="f-install">
      <div class="f-fdiv">
        <h2>{!! __('Choose install language') !!}</h2>
        <form class="f-form" method="post" action="{!! $p->formAction !!}">
          <div>
            <label class="f-child1">{!! __('Install language') !!}</label>
            <select class="f-ctrl" id="id-installlang" name="installlang">
@foreach($p->installLangs as $cur)
@if(isset($cur[1]))
              <option value="{{ $cur[0] }}" selected>{{ $cur[0] }}</option>
@else
              <option value="{{ $cur[0] }}">{{ $cur[0] }}</option>
@endif
@endforeach
            </select>
            <label class="f-child4">{!! __('Choose install language info') !!}</label>
          </div>
          <div>
            <input class="f-btn" type="submit" name="changelang" value="{!! __('Change language') !!}">
          </div>
        </form>
      </div>
    </section>
@endif
@if(empty($p->fIswev['e']))
    <section class="f-main f-install">
      <div class="f-fdiv">
        <h2>{!! __('Install', $p->rev) !!}</h2>
        <form class="f-form" method="post" action="{!! $p->formAction !!}" autocomplete="off">
          <input type="hidden" name="installlang" value="{!! $p->installLang !!}">
          <div class="f-finfo">
            <h3>{!! __('Database setup') !!}</h3>
            <p>{!! __('Info 1') !!}</p>
          </div>
          <div>
            <label class="f-child1 f-req">{!! __('Database type') !!}</label>
            <select class="f-ctrl" id="id-dbtype" name="dbtype">
@foreach($p->dbTypes as $key => $cur)
@if(empty($cur[1]))
              <option value="{{ $key }}">{{ $cur[0] }}</option>
@else
              <option value="{{ $key }}" selected>{{ $cur[0] }}</option>
@endif
@endforeach
            </select>
            <label class="f-child4">{!! __('Info 2') !!}</label>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-dbhost">{!! __('Database server hostname') !!}</label>
            <input required class="f-ctrl" id="id-dbhost" type="text" name="dbhost" value="{{ $p->dbhost }}">
            <label class="f-child4">{!! __('Info 3') !!}</label>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-dbname">{!! __('Database name') !!}</label>
            <input required class="f-ctrl" id="id-dbname" type="text" name="dbname" value="{{ $p->dbname }}">
            <label class="f-child4">{!! __('Info 4') !!}</label>
          </div>
          <div>
            <label class="f-child1" for="id-dbuser">{!! __('Database username') !!}</label>
            <input class="f-ctrl" id="id-dbuser" type="text" name="dbuser" value="{{ $p->dbuser }}">
          </div>
          <div>
            <label class="f-child1" for="id-dbpass">{!! __('Database password') !!}</label>
            <input class="f-ctrl" id="id-dbpass" type="password" name="dbpass">
            <label class="f-child4">{!! __('Info 5') !!}</label>
          </div>
          <div>
            <label class="f-child1" for="id-dbprefix">{!! __('Table prefix') !!}</label>
            <input class="f-ctrl" id="id-dbprefix" type="text" name="dbprefix" value="{{ $p->dbprefix }}" maxlength="40" pattern="^[a-zA-Z][a-zA-Z\d_]*$">
            <label class="f-child4">{!! __('Info 6') !!}</label>
          </div>
          <div class="f-finfo">
            <h3>{!! __('Administration setup') !!}</h3>
            <p>{!! __('Info 7') !!}</p>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-username">{!! __('Administrator username') !!}</label>
            <input required class="f-ctrl" id="id-username" type="text" name="username" value="{{ $p->username }}" maxlength="25" pattern="^.{2,25}$">
            <label class="f-child4">{!! __('Info 8') !!}</label>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-password">{!! __('Administrator passphrase') !!}</label>
            <input required class="f-ctrl" id="id-password" type="password" name="password" pattern="^.{16,}$">
            <label class="f-child4">{!! __('Info 9') !!}</label>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-email">{!! __('Administrator email') !!}</label>
            <input required class="f-ctrl" id="id-email" type="text" name="email" value="{{ $p->email }}" maxlength="80" pattern=".+@.+">
            <label class="f-child4">{!! __('Info 10') !!}</label>
          </div>
          <div class="f-finfo">
            <h3>{!! __('Board setup') !!}</h3>
            <p>{!! __('Info 11') !!}</p>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-title">{!! __('Board title') !!}</label>
            <input required class="f-ctrl" id="id-title" type="text" name="title" value="{{ $p->title }}">
          </div>
          <div>
            <label class="f-child1 f-req" for="id-descr">{!! __('Board description') !!}</label>
            <input required class="f-ctrl" id="id-descr" type="text" name="descr" value="{{ $p->descr }}">
          </div>
          <div>
            <label class="f-child1 f-req" for="id-baseurl">{!! __('Base URL') !!}</label>
            <input required class="f-ctrl" id="id-baseurl" type="text" name="baseurl" value="{{ $p->baseurl }}">
          </div>
@if(is_array($p->defaultLangs))
          <div>
            <label class="f-child1 f-req">{!! __('Default language') !!}</label>
            <select class="f-ctrl" id="id-defaultlang" name="defaultlang">
@foreach($p->defaultLangs as $cur)
@if(isset($cur[1]))
              <option value="{{ $cur[0] }}" selected>{{ $cur[0] }}</option>
@else
              <option value="{{ $cur[0] }}">{{ $cur[0] }}</option>
@endif
@endforeach
            </select>
          </div>
@else
          <input type="hidden" name="defaultlang" value="{!! $p->defaultLangs !!}">
@endif
          <div>
            <label class="f-child1 f-req">{!! __('Default style') !!}</label>
            <select class="f-ctrl" id="id-defaultstyle" name="defaultstyle">
@foreach($p->defaultStyles as $cur)
@if(isset($cur[1]))
              <option value="{{ $cur[0] }}" selected>{{ $cur[0] }}</option>
@else
              <option value="{{ $cur[0] }}">{{ $cur[0] }}</option>
@endif
@endforeach
            </select>
          </div>
          <div>
            <input class="f-btn" type="submit" name="startinstall" value="{!! __('Start install') !!}">
          </div>
        </form>
      </div>
    </section>
@endif
<!-- debuginfo -->
  </div>
</body>
</html>
