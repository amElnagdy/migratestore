document.addEventListener("DOMContentLoaded", function () {
  const fileInput = document.getElementById("json_zip_file");
  const fileLabel = document.querySelector(".ms-file-label");
  const fileName = document.querySelector(".ms-file-name");
  const dropZone = document.querySelector(".ms-file-upload");

  // Handle file selection
  fileInput.addEventListener("change", function (e) {
    if (this.files && this.files[0]) {
      fileName.textContent = this.files[0].name;
      dropZone.classList.add("has-file");
    }
  });

  // Handle drag and drop
  ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
    dropZone.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ["dragenter", "dragover"].forEach((eventName) => {
    dropZone.addEventListener(eventName, highlight, false);
  });

  ["dragleave", "drop"].forEach((eventName) => {
    dropZone.addEventListener(eventName, unhighlight, false);
  });

  function highlight(e) {
    dropZone.classList.add("drag-hover");
  }

  function unhighlight(e) {
    dropZone.classList.remove("drag-hover");
  }

  dropZone.addEventListener("drop", handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    fileInput.files = files;
    if (files && files[0]) {
      fileName.textContent = files[0].name;
      dropZone.classList.add("has-file");
    }
  }
});
