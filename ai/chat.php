<?php
session_start();
require_once __DIR__.'/../includes/header.php';
?>

<div class="container mt-4">

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        🤖 AI ERP Assistant
    </div>

    <div class="card-body">

        <div id="chat-box" style="height:400px;overflow-y:auto;"></div>

        <div class="input-group mt-3">
            <input type="text" id="msg" class="form-control" placeholder="Nhập lệnh...">
            <button class="btn btn-primary" onclick="sendMsg()">Gửi</button>
        </div>

    </div>
</div>

</div>

<script>

function addMsg(text,type){

let div = document.createElement("div");

div.className = "mb-2 p-2 rounded";

if(type=="user"){
    div.classList.add("bg-primary","text-white","text-end");
}else{
    div.classList.add("bg-light");
}

div.innerText = text;

document.getElementById("chat-box").appendChild(div);

// auto scroll
document.getElementById("chat-box").scrollTop = 99999;
}

function sendMsg(){

let input = document.getElementById("msg");
let text = input.value;

if(!text) return;

addMsg(text,"user");

fetch("chat_api.php",{
method:"POST",
headers:{"Content-Type":"application/x-www-form-urlencoded"},
body:"msg="+encodeURIComponent(text)
})
.then(async r=>{
    const t = await r.text();
    try { return JSON.parse(t); }
    catch(e){
        console.error(t);
        return {message:"❌ Server lỗi"};
    }
})
.then(res=>{

addMsg(res.message,"bot");

if(res.open_modal){
    window.location.href="/quanlykho/catalog.php?open_product="+res.product_id;
}

});

input.value="";
}

// ENTER gửi
document.getElementById("msg").addEventListener("keypress", function(e){
    if(e.key === "Enter"){
        e.preventDefault();
        sendMsg();
    }
});

</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>