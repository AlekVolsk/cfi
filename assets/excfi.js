/**
 * @package     Joomla.Plugin
 * @subpackage  System.excfi
 * @copyright   Copyright (C) Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

document.addEventListener('DOMContentLoaded', function () {

    var excfiBtnToolbar = document.getElementById('js-excfi-toolbarbtn');
    var excfiBtnClose = document.getElementById('js-excfi-wellclose');
    var excfiWell = document.getElementById('js-excfi-well');
    var excfiDropArea = document.getElementById('js-excfi-dropzone');
    var excfiDropInput = document.getElementById('js-excfi-file');
    var excfiDropLabel = document.getElementById('js-excfi-importlabel');
    var excfiCbConvert = document.getElementById('js-excfi-convert');
    var excfiExportArea = document.getElementById('js-excfi-expzone');
    var excfiSelCategories = document.getElementById('js-excfi-categories');
    var excfiBtnExport = document.getElementById('js-excfi-export');
    var excfiLabelExport = document.getElementById('js-excfi-exportlabel');

    if (excfiBtnToolbar && excfiWell) {
        excfiBtnToolbar.addEventListener('click', excfi_toggleWell, false);
        excfiBtnClose.addEventListener('click', excfi_toggleWell, false);

        excfiDropArea.addEventListener('dragenter', excfi_preventDefaults, false);
        excfiDropArea.addEventListener('dragover', excfi_preventDefaults, false);
        excfiDropArea.addEventListener('dragleave', excfi_preventDefaults, false);
        excfiDropArea.addEventListener('drop', excfi_preventDefaults, false);
        excfiDropArea.addEventListener('dragenter', excfi_highlight, false);
        excfiDropArea.addEventListener('dragover', excfi_highlight, false);
        excfiDropArea.addEventListener('dragleave', excfi_unhighlight, false);
        excfiDropArea.addEventListener('drop', excfi_unhighlight, false);
        excfiDropArea.addEventListener('drop', excfi_handleDrop, false);

        excfiDropInput.addEventListener('change', excfi_handleFiles, false);

        excfiBtnExport.addEventListener('click', excfi_export, false);
    }

    function excfi_toggleWell() {
        if (excfiWell.classList.contains('hidden')) {
            excfi_clearState();
        }
        excfiWell.classList.toggle('hidden');
    }

    function excfi_clearState() {
        excfiDropArea.classList.remove('alert-success', 'alert-error', 'excfi-dropzone-highlight');
        excfiDropLabel.innerHTML = excfiDropArea.dataset.ready;
    }

    function excfi_preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function excfi_highlight() {
        excfiDropArea.classList.remove('alert-success', 'alert-error');
        excfiDropArea.classList.add('excfi-dropzone-highlight');
    }

    function excfi_unhighlight() {
        excfiDropArea.classList.remove('excfi-dropzone-highlight');
    }

    function excfi_handleDrop(e) {
        var dt = e.dataTransfer;
        var files = dt.files;
        excfi_handleDropFiles(files);
    }

    function excfi_handleFiles() {
        excfi_handleDropFiles(this.files);
    }

    function excfi_handleDropFiles(files) {
        files = [...files];
        files.forEach(excfi_uploadFile);
    }

    function excfi_uploadFile(file, i) {
        excfiLabelExport.innerHTML = '';
        excfiDropArea.classList.remove('alert-success', 'alert-error', 'excfi-dropzone-highlight');
        excfiDropLabel.innerHTML = excfiDropArea.dataset.worktitle;
        excfiDropArea.style.pointerEvents = 'none';
        excfiExportArea.style.pointerEvents = 'none';
        var url = location.protocol + '//' + location.host + Joomla.getOptions('system.paths')['base'] +
            '/index.php?option=com_ajax&group=system&plugin=excfi&method=post&format=raw';
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
                        excfiDropArea.classList.remove('excfi-dropzone-highlight');
                        excfiDropArea.classList.add('alert-' + (response.result ? 'success' : 'error'));
                        excfiDropLabel.innerHTML = '<strong>' + (response.result ? excfiDropArea.dataset.success : excfiDropArea.dataset.error) + '</strong><br>' + response.message;
                    } catch (e) {
                        excfiDropArea.classList.remove('excfi-dropzone-highlight');
                        excfiDropArea.classList.add('alert-error');
                        excfiDropLabel.innerHTML = '<strong>' + excfiDropArea.dataset.error + '</strong><span>' + xhr.response + '</span>';
                    }
                    excfiDropArea.style.pointerEvents = 'auto';
                    excfiExportArea.style.pointerEvents = 'auto';
                    if (response && response.result) {
                        var t = 10;
                        setInterval(function () {
                            t--;
                            document.getElementById('excfi-result-counter').innerText = t;
                            if (!t) {
                                location.reload();
                            }
                        }, 1000);
                    }
                } else {
                    excfiDropArea.classList.remove('excfi-dropzone-highlight');
                    excfiDropArea.classList.add('alert-error');
                    excfiDropLabel.innerHTML = '<strong>' + xhr.status + '</strong><span>Unknown error, look at the log</span>';
                    excfiDropArea.style.pointerEvents = 'auto';
                    excfiExportArea.style.pointerEvents = 'auto';
                }
            }
        });

        formData.append('excfifile', file);
        formData.append('excfistate', 'import');
        formData.append('excficonvert', Number(excfiCbConvert.checked));
        formData.append(Joomla.getOptions('csrf.token'), '1');
        xhr.send(formData);
    }

    function excfi_export()
    {
        if (excfiSelCategories.value < 1) {
            excfiLabelExport.classList.add('text-error');
            excfiLabelExport.innerHTML = excfiBtnExport.dataset.error + '<br>' + excfiBtnExport.dataset.nosel;
            return;
        }

        excfiLabelExport.innerHTML = '';
        excfiLabelExport.classList.remove('text-success', 'text-error');
        excfiDropArea.style.pointerEvents = 'none';
        excfiExportArea.style.pointerEvents = 'none';
        var url = location.protocol + '//' + location.host + Joomla.getOptions('system.paths')['base'] +
            '/index.php?option=com_ajax&group=system&plugin=excfi';
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
                            excfiLabelExport.classList.add('text-success');
                            excfiLabelExport.innerHTML = excfiBtnExport.dataset.success;
                            window.location = url + '&format=raw&excfistate=download&f=' + response.f + '&' + Joomla.getOptions('csrf.token') + '=1';
                        } else {
                            excfiLabelExport.classList.add('text-error');
                            excfiLabelExport.innerHTML = excfiBtnExport.dataset.error + '<br>' + response.message;
                        }
                    } catch (e) {
                        excfiLabelExport.classList.add('text-error');
                        excfiLabelExport.innerHTML = excfiBtnExport.dataset.error + '<br>' + xhr.response;
                    }
                    excfiDropArea.style.pointerEvents = 'auto';
                    excfiExportArea.style.pointerEvents = 'auto';
                } else {
                    excfiDropArea.classList.remove('excfi-dropzone-highlight');
                    excfiDropArea.classList.add('alert-error');
                    excfiLabelExport.innerHTML = '<strong>' + xhr.status + '</strong><span>Unknown error, look at the log</span>';
                    excfiDropArea.style.pointerEvents = 'auto';
                    excfiExportArea.style.pointerEvents = 'auto';
                }
            }
        });

        formData.append('excficat', excfiSelCategories.value);
        formData.append('excfistate', 'export');
        formData.append('excficonvert', excfiCbConvert.checked);
        formData.append(Joomla.getOptions('csrf.token'), '1');
        xhr.send(formData);
    }

});
