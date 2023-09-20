@extends ('layouts/admin')
      <section id="fork-phpinfo" class="f-admin">
        <h2>phpinfo()</h2>
        <div id="id-phpinfo-div">
          {!! $p->phpinfo !!}
        </div>
      </section>
