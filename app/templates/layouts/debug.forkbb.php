      <aside id="fork-debug">
        <p class="f-sim-header">{!! __('Debug table') !!}</p>
        <p id="id-fdebugtime">[ {!! __(['Querytime', num($p->time, 3), $p->numQueries]) !!} - {!! __(['Memory usage', size($p->memory)]) !!} {!! __(['Peak usage', size($p->peak)]) !!} ]</p>
@if ($p->queries)
        <table id="fork-dgtable">
          <thead id="fork-dgthead">
            <tr>
              <th class="f-tcl" scope="col">{!! __('Query times') !!}</th>
              <th class="f-tcr" scope="col">{!! __('Query') !!}</th>
            </tr>
          </thead>
          <tbody>
    @foreach ($p->queries as $cur)
            <tr>
              <td class="f-tcl">{{ num($cur[1], 3) }}</td>
              <td class="f-tcr">{{ $cur[0] }}</td>
            </tr>
    @endforeach
            <tr>
              <td class="f-tcl">{{ num($p->total, 3) }}</td>
              <td class="f-tcr"></td>
            </tr>
          </tbody>
        </table>
@endif
      </aside>
