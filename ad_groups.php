<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/ldaps_config.php';

session_start();
if (!isset($_SESSION['user']) && !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Gruppenverwaltung</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
body { font-family:'Segoe UI',sans-serif; background:#1b1b2c; color:#fff; margin:0; padding:20px; }
h1 { color:#00d1ff; margin-bottom:20px; }
#groups_wrapper {background: none;/* entferne Hintergrundfarbe */padding: 0;/* entferne Padding */
    border-radius: 0;       /* keine abgerundeten Ecken */
    box-shadow: none;       /* entferne Schatten */
}

#memberModal, #moveModal, #actionModal {
    display:none;
    position:fixed;
    top:30%;
    left:50%;
    transform:translateX(-50%);
    background: linear-gradient(145deg,#1f1f2e,#2b2b44);
    color:#fff;
    border-radius:12px;
    box-shadow:0 4px 20px rgba(0,0,0,0.5);
    padding:25px;
    width:420px;
    max-height:75%;
    overflow:auto;
    z-index:2000;
    animation:fadeIn 0.3s ease-out;
}
#memberModal h2, #moveModal h2 { color:#00d1ff; margin-top:0; font-size:1.5em; }
#memberModal select, #moveModal select { padding:10px; border-radius:6px; border:none; width:100%; background:#2f2f4b; color:#fff; font-size:1em; margin-bottom:15px; }
#memberModal button, #moveModal button, #actionModal button { margin-top:10px; }
#memberModal .close, #moveModal .close, #actionModal .close { float:right; cursor:pointer; color:#ff5f7a; font-size:1.2em; }
#memberModal .close:hover, #moveModal .close:hover, #actionModal .close:hover { color:#ff1f3b; }

.modal-buttons { display:flex; justify-content:flex-end; gap:10px; margin-top:10px; }
.btn { padding:8px 18px; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:0.2s; }
.btn-primary { background:#00d1ff; color:#1b1b2c; }
.btn-primary:hover { background:#00a6cc; }
.btn-secondary { background:#55597b; color:#fff; }
.btn-secondary:hover { background:#444670; }

#toastContainer { position:fixed; top:20px; right:20px; z-index:3000; }
.toast { background:#333; color:#fff; padding:12px 20px; margin-bottom:10px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.2); opacity:0; transform:translateX(100%); transition: transform 0.4s ease, opacity 0.4s ease; }
.toast.show { opacity:1; transform:translateX(0); }
.toast.success { background-color: #28a745; }
.toast.error { background-color: #dc3545; }

.move-group {
    background: #00d1ff;
    color: #1b1b2c;
    border: none;
    border-radius: 8px;
    padding: 6px 14px;
    font-weight:600;
    cursor:pointer;
    margin-left:5px;
    transition:0.2s;
}
.move-group:hover {
    background: #00a6cc;
    box-shadow: 0 0 6px #00d1ff;
}


@keyframes fadeIn { from {opacity:0; transform:translate(-50%,-10%);} to {opacity:1; transform:translateX(-50%);} }
</style>
<link rel="stylesheet" href="css/ad_groups.css">
</head>
<body>
<div id="toastContainer"></div>
<h1>Active Directory Gruppen</h1>
<table id="groups" class="display" style="width:100%">
    <thead>
        <tr>
            <th>Gruppenname</th>
            <th>Aktionen</th>
        </tr>
    </thead>
</table>

<!-- Mitglieder Modal -->
<div id="memberModal">
    <span class="close">✖</span>
    <h2 id="modalGroupName"></h2>
    <div>
        <h3>Mitglieder der Gruppe</h3>
        <select id="groupMembers" multiple size="8" style="width:100%"></select>
        <button id="removeMembersBtn">Entfernen</button>
    </div>
    <div>
        <h3>Verfügbare Benutzer</h3>
        <select id="availableUsers" multiple size="8" style="width:100%"></select>
        <button id="addMembersBtn">Hinzufügen</button>
    </div>
</div>

<!-- Verschiebe Modal -->
<div id="moveModal">
    <span class="close">✖</span>
    <h2>Gruppe verschieben</h2>
    <p>Wähle die Ziel-OU:</p>
    <select id="moveOuSelect" style="width:100%"></select>
    <div class="modal-buttons">
        <button id="moveCancel" class="btn btn-secondary">Abbrechen</button>
        <button id="moveConfirm" class="btn btn-primary">Verschieben</button>
    </div>
</div>

<!-- Action Modal für Löschen/Umbennen -->
<div id="actionModal">
    <p id="actionMessage"></p>
    <input type="text" id="actionInput" style="width:100%; display:none;" />
    <button id="actionCancel" class="btn btn-secondary">Abbrechen</button>
    <button id="actionConfirm" class="btn btn-primary">OK</button>
</div>

<script>
function showToast(message, type='success', duration=3000){
    const container = $('#toastContainer');
    const toast = $('<div class="toast"></div>').addClass(type).text(message);
    container.append(toast);
    setTimeout(()=>toast.addClass('show'),50);
    setTimeout(()=>{ toast.removeClass('show'); setTimeout(()=>toast.remove(),400); }, duration);
}

$(document).ready(function(){
    var table = $('#groups').DataTable({
        "ajax": { "url": "ad_groups_ajax.php", "dataSrc": "data", "error": (xhr,error)=>showToast("Die Gruppen konnten nicht geladen werden.",'error') },
        "columns": [ { "data": "cn" }, { "data": "actions" } ]
    });

    // Modal schließen
    $('#memberModal .close, #moveModal .close, #actionModal .close').click(()=>$('#memberModal,#moveModal,#actionModal').hide());
    $(document).on('keydown',(e)=>{ if(e.key==="Escape") $('#memberModal,#moveModal,#actionModal').hide(); });
    $(document).mouseup((e)=>{ let modal=$("#memberModal,#moveModal,#actionModal"); if(!modal.is(e.target)&&modal.has(e.target).length===0) modal.hide(); });

    // Gruppen Aktionen: Löschen
    $('#groups').on('click', '.delete-group', function(){
        let dn = $(this).data('dn');
        $('#actionMessage').text("Gruppe wirklich löschen?");
        $('#actionInput').hide();
        $('#actionModal').show();
        $('#actionCancel').off('click').on('click',()=>$('#actionModal').hide());
        $('#actionConfirm').off('click').on('click', ()=>{
            $.post('ad_group_action.php',{action:'delete', group_dn:dn}, function(res){
                showToast(res.message,'success'); table.ajax.reload();
            },'json');
            $('#actionModal').hide();
        });
    });

    // Gruppen Aktionen: Umbennen
    $('#groups').on('click', '.rename-group', function(){
        let dn = $(this).data('dn');
        $('#actionMessage').text("Neuer Gruppenname:");
        $('#actionInput').val('').show();
        $('#actionModal').show();
        $('#actionCancel').off('click').on('click',()=>$('#actionModal').hide());
        $('#actionConfirm').off('click').on('click', ()=>{
            let newName = $('#actionInput').val(); if(!newName) return;
            $.post('ad_group_action.php',{action:'rename', group_dn:dn, new_name:newName}, function(res){
                showToast(res.message,'success'); table.ajax.reload();
            },'json');
            $('#actionModal').hide();
        });
    });

    // Mitglieder Modal
    $('#groups').on('click', '.show-members', function(){
        let dn=$(this).data('dn'), groupName=$(this).data('name');
        $('#modalGroupName').text(groupName).show();
        $('#memberModal').show();
        $('#groupMembers,#availableUsers').empty();
        $.getJSON('ad_group_members.php',{group_dn:dn},function(res){
            if(res.members) res.members.forEach(m=>$('#groupMembers').append(`<option value="${m.sAMAccountName}">${m.cn} (${m.sAMAccountName})</option>`));
            if(res.allUsers) res.allUsers.forEach(u=>{ if(!res.members.some(m=>m.sAMAccountName===u.sAMAccountName)) $('#availableUsers').append(`<option value="${u.sAMAccountName}">${u.cn} (${u.sAMAccountName})</option>`); });
        }).fail((xhr,status,error)=>showToast("Fehler beim Laden der Mitglieder: "+error,'error'));

        // Hinzufügen
        $('#addMembersBtn').off('click').on('click', function(){
            let selected = $('#availableUsers').val(); if(!selected||selected.length===0) return;
            $.ajax({url:'ad_group_action.php',method:'POST',data:{action:'addMember',group_dn:dn,'user[]':selected},traditional:true,dataType:'json',
            success: res=>{ showToast(res.message,'success'); table.ajax.reload(); $('#memberModal').hide(); },
            error:(xhr,status,error)=>showToast("Fehler beim Hinzufügen: "+error,'error')});
        });

        // Entfernen
        $('#removeMembersBtn').off('click').on('click', function(){
            let selected = $('#groupMembers').val(); if(!selected||selected.length===0) return;
            $.ajax({url:'ad_group_action.php',method:'POST',data:{action:'removeMember',group_dn:dn,'user[]':selected},traditional:true,dataType:'json',
            success: res=>{ showToast(res.message,'success'); table.ajax.reload(); $('#memberModal').hide(); },
            error:(xhr,status,error)=>showToast("Fehler beim Entfernen: "+error,'error')});
        });
    });

    // Verschiebe Modal
    $('#groups').on('click', '.move-group', function(){
        let dn=$(this).data('dn');
        $.getJSON('ad_ou_list.php',function(res){
            if(!res.ous){ showToast("Keine OUs gefunden",'error'); return; }
            let select=$('#moveOuSelect'); select.empty();
            res.ous.forEach(ou=>select.append(`<option value="${ou.dn}">${ou.name}</option>`));
            $('#moveModal').show();
            $('#moveCancel, #moveModal .close').off('click').on('click',()=>$('#moveModal').hide());
            $('#moveConfirm').off('click').on('click', ()=>{
                let targetOU=select.val(); if(!targetOU) return;
                $.post('ad_group_action.php',{action:'move', group_dn:dn, target_ou:targetOU},function(res){
                    showToast(res.message,'success'); table.ajax.reload(); $('#moveModal').hide();
                },'json').fail((xhr,status,error)=>showToast("Fehler beim Verschieben: "+error,'error'));
            });
        });
    });
});
</script>
</body>
</html>

