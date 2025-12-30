Dropzone.autoDiscover = false;

document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        if (typeof Dropzone === "undefined") {
        console.warn("Dropzone CDN not loaded, fallback to native file input.");
        document.querySelectorAll('input[type="file"]').forEach(i => i.style.display = "");
        return;
    }
        // tránh xử lý lại
        if (input.dataset.dropzoneApplied === "1") return;
        input.dataset.dropzoneApplied = "1";

        let form = input.closest("form");

        // Ẩn input gốc
        input.style.display = "none";

        // Tạo dropzone UI
        let dzEl = document.createElement("div");
        dzEl.className = "dropzone universal-dropzone";
        input.parentNode.insertBefore(dzEl, input);

        let dz = new Dropzone(dzEl, {
            url: form ? form.action : "/",
            autoProcessQueue: false,
            maxFiles: input.multiple ? null : 1,
            paramName: input.name,
            acceptedFiles: input.accept || null,
            clickable: true,
            addRemoveLinks: true,
        });

        dz.on("addedfile", function (file) {

            // ⚠️ Đồng bộ file
            let dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            // 🔥 CỰC KỲ QUAN TRỌNG
            // Kích hoạt lại toàn bộ logic cũ
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        dz.on("removedfile", function () {
            input.value = "";
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

});
