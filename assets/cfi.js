/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

document.addEventListener('DOMContentLoaded', function () {

    var cfiBtnToolbar = document.getElementById('js-cfi-toolbarbtn');
    var cfiBtnClose = document.getElementById('js-cfi-wellclose');
    var cfiWell = document.getElementById('js-cfi-well');
    var cfiDropArea = document.getElementById('js-cfi-dropzone');
    var cfiDropInput = document.getElementById('js-cfi-file');
    var cfiDropLabel = document.getElementById('js-cfi-importlabel');
    var cfiCbConvert = document.getElementById('js-cfi-convert');
    var cfiExportArea = document.getElementById('js-cfi-expzone');
    var cfiSelCategories = document.getElementById('js-cfi-categories');
    var cfiBtnExport = document.getElementById('js-cfi-export');
    var cfiLabelExport = document.getElementById('js-cfi-exportlabel');

    if (cfiBtnToolbar && cfiWell) {
        cfiBtnToolbar.addEventListener('click', cfi_toggleWell, false);
        cfiBtnClose.addEventListener('click', cfi_toggleWell, false);

        cfiDropArea.addEventListener('dragenter', cfi_preventDefaults, false);
        cfiDropArea.addEventListener('dragover', cfi_preventDefaults, false);
        cfiDropArea.addEventListener('dragleave', cfi_preventDefaults, false);
        cfiDropArea.addEventListener('drop', cfi_preventDefaults, false);
        cfiDropArea.addEventListener('dragenter', cfi_highlight, false);
        cfiDropArea.addEventListener('dragover', cfi_highlight, false);
        cfiDropArea.addEventListener('dragleave', cfi_unhighlight, false);
        cfiDropArea.addEventListener('drop', cfi_unhighlight, false);
        cfiDropArea.addEventListener('drop', cfi_handleDrop, false);

        cfiDropInput.addEventListener('change', cfi_handleFiles, false);

        cfiBtnExport.addEventListener('click', cfi_export, false);
    }

    function cfi_toggleWell() {
        if (cfiWell.classList.contains('hidden')) {
            cfi_clearState();
        }
        cfiWell.classList.toggle('hidden');
    }

    function cfi_clearState() {
        cfiDropArea.classList.remove('alert-success', 'alert-error', 'cfi-dropzone-highlight');
        cfiDropLabel.innerHTML = cfiDropArea.dataset.ready;
    }

    function cfi_preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function cfi_highlight() {
        cfiDropArea.classList.remove('alert-success', 'alert-error');
        cfiDropArea.classList.add('cfi-dropzone-highlight');
    }

    function cfi_unhighlight() {
        cfiDropArea.classList.remove('cfi-dropzone-highlight');
    }

    function cfi_handleDrop(e) {
        var dt = e.dataTransfer;
        var files = dt.files;
        cfi_handleDropFiles(files);
    }

    function cfi_handleFiles() {
        cfi_handleDropFiles(this.files);
    }

    function cfi_handleDropFiles(files) {
        files = [...files];
        files.forEach(cfi_uploadFile);
    }

    function cfi_uploadFile(file, i) {
        cfiLabelExport.innerHTML = '';
        cfiDropArea.classList.remove('alert-success', 'alert-error', 'cfi-dropzone-highlight');
        cfiDropLabel.innerHTML = cfiDropArea.dataset.worktitle;
        cfiDropArea.style.pointerEvents = 'none';
        cfiExportArea.style.pointerEvents = 'none';
        var url = location.protocol + '//' + location.host + Joomla.getOptions('system.paths')['base'] +
            '/index.php?option=com_ajax&group=system&plugin=cfi&method=post&format=raw';
        var xhr = new XMLHttpRequest();
        var formData = new FormData();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', Joomla.getOptions('csrf.token'));

        xhr.addEventListener('readystatechange', function (e) {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    var response = false;
                    try {
                        response = JSON.parse(xhr.response);
                        cfiDropArea.classList.remove('cfi-dropzone-highlight');
                        cfiDropArea.classList.add('alert-' + (response.result ? 'success' : 'error'));
                        cfiDropLabel.innerHTML = '<strong>' + (response.result
                          ? cfiDropArea.dataset.success
                          : cfiDropArea.dataset.error) + '</strong><br>' + response.message;
                    } catch (e) {
                        cfiDropArea.classList.remove('cfi-dropzone-highlight');
                        cfiDropArea.classList.add('alert-error');
                        cfiDropLabel.innerHTML = '<strong>' + cfiDropArea.dataset.error + '</strong><span>' +
                          xhr.response + '</span>';
                    }
                    cfiDropArea.style.pointerEvents = 'auto';
                    cfiExportArea.style.pointerEvents = 'auto';
                    if (response && response.result) {
                        var t = 10;
                        setInterval(function () {
                            t--;
                            document.getElementById('cfi-result-counter').innerText = t;
                            if (!t) {
                                location.reload();
                            }
                        }, 1000);
                    }
                } else {
                    cfiDropArea.classList.remove('cfi-dropzone-highlight');
                    cfiDropArea.classList.add('alert-error');
                    cfiDropLabel.innerHTML = '<strong>' + xhr.status +
                      '</strong><span>Unknown error, look at the log</span>';
                    cfiDropArea.style.pointerEvents = 'auto';
                    cfiExportArea.style.pointerEvents = 'auto';
                }
            }
        });

        formData.append('cfifile', file);
        formData.append('cfistate', 'import');
        formData.append('cficonvert', Number(cfiCbConvert.checked));
        formData.append(Joomla.getOptions('csrf.token'), '1');
        xhr.send(formData);
    }

    function cfi_export()
    {
        if (cfiSelCategories.value < 1) {
            cfiLabelExport.classList.add('text-error');
            cfiLabelExport.innerHTML = cfiBtnExport.dataset.error + '<br>' + cfiBtnExport.dataset.nosel;
            return;
        }

        cfiLabelExport.innerHTML = '';
        cfiLabelExport.classList.remove('text-success', 'text-error');
        cfiDropArea.style.pointerEvents = 'none';
        cfiExportArea.style.pointerEvents = 'none';
        var url = location.protocol + '//' + location.host + Joomla.getOptions('system.paths')['base'] +
            '/index.php?option=com_ajax&group=system&plugin=cfi';
        var xhr = new XMLHttpRequest();
        var formData = new FormData();
        xhr.open('POST', url + '&format=json', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', Joomla.getOptions('csrf.token'));

        xhr.addEventListener('readystatechange', function (e) {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    try {
                        var response = JSON.parse(xhr.response);
                        if (response.result) {
                            cfiLabelExport.classList.add('text-success');
                            cfiLabelExport.innerHTML = cfiBtnExport.dataset.success;
                            window.location = url + '&format=raw&cfistate=download&f=' + response.f + '&' +
                              Joomla.getOptions('csrf.token') + '=1';
                        } else {
                            cfiLabelExport.classList.add('text-error');
                            cfiLabelExport.innerHTML = cfiBtnExport.dataset.error + '<br>' + response.message;
                        }
                    } catch (e) {
                        cfiLabelExport.classList.add('text-error');
                        cfiLabelExport.innerHTML = cfiBtnExport.dataset.error + '<br>' + xhr.response;
                    }
                    cfiDropArea.style.pointerEvents = 'auto';
                    cfiExportArea.style.pointerEvents = 'auto';
                } else {
                    cfiDropArea.classList.remove('cfi-dropzone-highlight');
                    cfiDropArea.classList.add('alert-error');
                    cfiLabelExport.innerHTML = '<strong>' + xhr.status +
                      '</strong><span>Unknown error, look at the log</span>';
                    cfiDropArea.style.pointerEvents = 'auto';
                    cfiExportArea.style.pointerEvents = 'auto';
                }
            }
        });

        formData.append('cficat', cfiSelCategories.value);
        formData.append('cfistate', 'export');
        formData.append('cficonvert', cfiCbConvert.checked);
        formData.append(Joomla.getOptions('csrf.token'), '1');
        xhr.send(formData);
    }

});
