    <section id="fork-debug">
      <h2>{!! __('Debug table') !!}</h2>
      <p id="id-fdebugtime">[ {!! __('Querytime', num($p->time, 3), $p->numQueries) !!} - {!! __('Memory usage', size($p->memory)) !!} {!! __('Peak usage', size($p->peak)) !!} ]</p>
@if ($p->queries)
      <table>
        <thead>
          <tr>
            <th class="tcl" scope="col">{!! __('Query times') !!}</th>
            <th class="tcr" scope="col">{!! __('Query') !!}</th>
          </tr>
        </thead>
        <tbody>
    @foreach ($p->queries as $cur)
          <tr>
            <td class="tcl">{{ num($cur[1], 3) }}</td>
            <td class="tcr">{{ $cur[0] }}</td>
          </tr>
    @endforeach
          <tr>
            <td class="tcl">{{ num($p->total, 3) }}</td>
            <td class="tcr"></td>
          </tr>
        </tbody>
      </table>
@endif
    </section>
