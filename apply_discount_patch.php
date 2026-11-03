<?php
/** Jalankan: php apply_discount_patch.php (dari root Laravel). */
declare(strict_types=1);
$root = getcwd();
$ops = [];
function edit(string $path, string $from, string $to): void {
    global $root, $ops;
    $full = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!isset($ops[$full])) {
        if (!is_file($full)) throw new RuntimeException("File tidak ditemukan: $path");
        $ops[$full] = file_get_contents($full);
    }
    $n = substr_count($ops[$full], $from);
    if ($n !== 1) throw new RuntimeException("Pola tidak cocok ($n): $path | ".substr($from,0,90));
    $ops[$full] = str_replace($from, $to, $ops[$full]);
}
try {
    $controller = 'app/Http/Controllers/ServiceQuotationController.php';
    edit($controller,
        "            'lines.*.qty' => ['required', 'integer', 'gt:0'],",
        "            'lines.*.qty' => ['required', 'integer', 'gt:0'],\n            'lines.*.discount_type' => ['nullable', 'in:none,percent,amount'],\n            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],");
    edit($controller,
        "                'depth' => (int) \$line->similar_depth,",
        "                'depth' => (int) \$line->similar_depth,\n                'discount_type' => (string) (\$line->discount_type ?? 'none'),\n                'discount_value' => (float) (\$line->discount_value ?? 0),");
    edit($controller,
        "                \$price = round((float) \$picked['retail_price'], 2);",
        "                \$price = round((float) \$picked['retail_price'], 2);\n                \$gross = round(\$qty * \$price, 2);\n                \$discountType = (string) (\$line['discount_type'] ?? 'none');\n                \$discountValue = round((float) (\$line['discount_value'] ?? 0), 2);\n                if (\$discountType === 'percent' && \$discountValue > 100) {\n                    throw new RuntimeException('Diskon persen tidak boleh melebihi 100%.');\n                }\n                if (\$discountType === 'none' && \$discountValue != 0) {\n                    throw new RuntimeException('Diskon tidak valid: pilih tipe diskon terlebih dahulu.');\n                }\n                // Diskon nominal berlaku pada subtotal BARIS (qty x harga).\n                \$discountAmount = match (\$discountType) {\n                    'percent' => round(\$gross * \$discountValue / 100, 2),\n                    'amount' => \$discountValue,\n                    default => 0.0,\n                };\n                if (\$discountAmount > \$gross) {\n                    throw new RuntimeException('Diskon tidak boleh melebihi total harga sparepart.');\n                }");
    edit($controller,
        "                    'line_total' => round(\$qty * \$price, 2),",
        "                    'line_total' => round(\$gross - \$discountAmount, 2),\n                    'discount_type' => \$discountType,\n                    'discount_value' => \$discountValue,\n                    'discount_amount' => \$discountAmount,");
    $view='resources/views/service-quotations/create.blade.php';
    edit($view,
        '<th class="text-end">Harga</th><th class="text-end">Jumlah</th><th></th>',
        '<th class="text-end">Harga</th><th>Diskon</th><th class="text-end">Jumlah Bersih (otomatis)</th><th></th>');
    edit($view,
        "if(!lines.length)body.innerHTML='<tr><td colspan=\"7\" class=\"text-muted text-center py-3\">Belum ada sparepart dipilih.</td></tr>';",
        "if(!lines.length)body.innerHTML='<tr><td colspan=\"8\" class=\"text-muted text-center py-3\">Belum ada sparepart dipilih.</td></tr>';");
    edit($view,
        'lines.push({...candidate, qty:1});renderLines();',
        "lines.push({...candidate, qty:1, discount_type:'none', discount_value:0});renderLines();");
    edit($view,
        'total+=Number(line.qty)*Number(line.retail_price);',
        'total+=netLine(line);');
    edit($view,
        "'<td class=\"text-end\">'+rupiah(line.retail_price)+'</td><td class=\"text-end\">'+rupiah(Number(line.qty)*Number(line.retail_price))+'</td><td></td>';",
        "'<td class=\"text-end\">'+rupiah(line.retail_price)+'</td><td></td><td class=\"text-end fw-semibold\">'+rupiah(netLine(line))+'</td><td></td>';");
    edit($view,
        '      tr.children[3].append(input);',
        "      tr.children[3].append(input);\n      const discType=document.createElement('select');discType.className='form-select form-select-sm mb-1';\n      [['none','Tanpa diskon'],['percent','Persen (%)'],['amount','Nominal (Rp)']].forEach(([v,t])=>discType.add(new Option(t,v)));\n      discType.name='lines['+i+'][discount_type]';discType.value=line.discount_type||'none';\n      const discValue=document.createElement('input');discValue.className='form-control form-control-sm';discValue.type='number';discValue.step='0.01';discValue.min='0';discValue.placeholder='Isi diskon';\n      discValue.name='lines['+i+'][discount_value]';discValue.value=String(line.discount_value||0);discValue.disabled=discType.value==='none';\n      discType.addEventListener('change',()=>{line.discount_type=discType.value;line.discount_value=0;discValue.value='0';discValue.disabled=discType.value==='none';renderTotal();});\n      discValue.addEventListener('input',()=>{line.discount_value=Number(discValue.value);renderTotal();});\n      tr.children[5].append(discType,discValue);");
    edit($view,
        "  function renderTotal(){let total=0;id('quotationLines').querySelectorAll('tr').forEach((tr,i)=>{if(!lines[i])return;const qty=Number(lines[i].qty);const x=Number.isFinite(qty) && qty>0 ? qty*Number(lines[i].retail_price) : 0;tr.children[5].textContent=rupiah(x);total+=x;});id('grandTotal').textContent=rupiah(total);}",
        "  function grossLine(line){return Number(line.qty)*Number(line.retail_price);}\n  function discountLine(line){const gross=grossLine(line), value=Number(line.discount_value||0);return line.discount_type==='percent'?Math.round(gross*value)/100:line.discount_type==='amount'?value:0;}\n  function netLine(line){return Math.max(0,grossLine(line)-discountLine(line));}\n  function discountError(line){const value=Number(line.discount_value||0), gross=grossLine(line), type=line.discount_type||'none';if(!Number.isFinite(value)||value<0)return 'Diskon harus angka positif atau nol.';if(type==='percent'&&value>100)return 'Diskon persen maksimal 100%.';if(type==='amount'&&value>gross)return 'Diskon nominal tidak boleh melebihi jumlah baris.';return '';}\n  function renderTotal(){let total=0;id('quotationLines').querySelectorAll('tr').forEach((tr,i)=>{if(!lines[i])return;const x=netLine(lines[i]);tr.children[6].textContent=rupiah(x);total+=x;});id('grandTotal').textContent=rupiah(total);}");
    edit($view,
        "      if (problem) {e.preventDefault();refreshQtyWarnings();alert(problem+' Penawaran tidak dapat disimpan.');return;}",
        "      if (problem) {e.preventDefault();refreshQtyWarnings();alert(problem+' Penawaran tidak dapat disimpan.');return;}\n      const discountProblem=discountError(line);\n      if(discountProblem){e.preventDefault();alert(discountProblem);return;}");
    // Existing edit seed holds discount values; new rows default to none.
    $show='resources/views/service-quotations/show.blade.php';
    edit($show,
        '<th class="text-end">Harga/Unit</th><th class="text-end">Total</th>',
        '<th class="text-end">Harga/Unit</th><th class="text-end">Diskon</th><th class="text-end">Total Bersih</th>');
    edit($show,
        '<td class="text-end">Rp {{ number_format((float)$line->unit_price, 0, \',\', \'.\') }}</td><td class="text-end">Rp {{ number_format((float)$line->line_total, 0, \',\', \'.\') }}</td>',
        '<td class="text-end">Rp {{ number_format((float)$line->unit_price, 0, \',\', \'.\') }}</td><td class="text-end">{{ ($line->discount_type ?? \'none\') === \'percent\' ? number_format((float)$line->discount_value, 2, \',\', \'.\').\'%\' : ((($line->discount_type ?? \'none\') === \'amount\') ? \'Rp \'.number_format((float)$line->discount_value, 0, \',\', \'.\') : \'-\') }}</td><td class="text-end">Rp {{ number_format((float)$line->line_total, 0, \',\', \'.\') }}</td>');
    edit($show, 'colspan="6" class="text-end">TOTAL PENAWARAN', 'colspan="7" class="text-end">TOTAL PENAWARAN');
    $print='resources/views/service-quotations/print.blade.php';
    edit($print, '<th class="right" style="width:17%">Jumlah</th>', '<th class="right" style="width:12%">Diskon</th><th class="right" style="width:17%">Jumlah</th>');
    edit($print,
        '<td class="right">Rp {{ number_format((float)$line->unit_price, 0, \',\', \'.\') }}</td><td class="right">Rp {{ number_format((float)$line->line_total, 0, \',\', \'.\') }}</td>',
        '<td class="right">Rp {{ number_format((float)$line->unit_price, 0, \',\', \'.\') }}</td><td class="right">{{ ($line->discount_type ?? \'none\') === \'percent\' ? number_format((float)$line->discount_value, 2, \',\', \'.\').\'%\' : ((($line->discount_type ?? \'none\') === \'amount\') ? \'Rp \'.number_format((float)$line->discount_value, 0, \',\', \'.\') : \'-\') }}</td><td class="right">Rp {{ number_format((float)$line->line_total, 0, \',\', \'.\') }}</td>');
    edit($print, '<th colspan="5" class="right">TOTAL SPAREPART', '<th colspan="6" class="right">TOTAL SPAREPART');
    $backup=$root.'/service_discount_backup_'.date('Ymd_His');
    foreach($ops as $file=>$content){$rel=substr($file,strlen($root)+1);$dest=$backup.'/'.$rel;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0777,true);if(!copy($file,$dest))throw new RuntimeException("Backup gagal: $file");}
    foreach($ops as $file=>$content){if(file_put_contents($file,$content)===false)throw new RuntimeException("Gagal tulis: $file");echo 'Updated '.substr($file,strlen($root)+1).PHP_EOL;}
    $src=__DIR__.'/database/migrations/2026_10_27_001500_add_service_line_discounts.php';
    $dest=$root.'/database/migrations/'.basename($src);
    if(!is_file($dest)){if(!copy($src,$dest))throw new RuntimeException('Migrasi gagal disalin.');echo 'Added database migration'.PHP_EOL;}
    echo "DONE. Backup: $backup\nRun: php artisan migrate --database=sqlsrv_apphub\nRun: php artisan optimize:clear\n";
} catch(Throwable $e){fwrite(STDERR,"PATCH STOPPED: ".$e->getMessage()."\nFile belum diubah jika validasi pola gagal.\n");exit(1);}
