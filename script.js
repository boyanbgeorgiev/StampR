document.addEventListener("DOMContentLoaded", () => {
    const uploadButton = document.getElementById("uploadButton");
    const fileInput = document.getElementById("fileInput");
    const fileNameLabel = document.getElementById("fileName");
  
    if (!uploadButton || !fileInput || !fileNameLabel) {
      console.error("❌ Missing key elements.");
      return;
    }
  
    // Show selected file name
    fileInput.addEventListener("change", () => {
      const file = fileInput.files[0];
      fileNameLabel.textContent = file ? file.name : "Няма избран";
    });
  
    // Upload file
    uploadButton.addEventListener("click", uploadFile);
  });
  
  function uploadFile() {
    const fileInput = document.getElementById("fileInput");
    const statusElement = document.getElementById("status");
    const resultBlock = document.getElementById("timestampResult");
    const resultTimestamp = document.getElementById("resultTimestamp");
    const resultHash = document.getElementById("resultHash");
    const resultSerial = document.getElementById("resultSerial");
  
    if (!fileInput || !fileInput.files.length) {
      setStatus("❌ Моля, изберете файл.", "error");
      return;
    }
  
    const file = fileInput.files[0];
  
    // Optional: Max size check before upload (5MB)
    const maxSizeMB = 5;
    if (file.size > maxSizeMB * 1024 * 1024) {
      setStatus(`❌ Файлът трябва да е под ${maxSizeMB} MB.`, "error");
      return;
    }
  
    setStatus("⏳ Обработка...", "loading");
  
    const formData = new FormData();
    formData.append("file", file);
  
    fetch("upload.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.text())
      .then(raw => {
        try {
          const data = JSON.parse(raw);
  
          // Server sent valid response, but not success
          if (data.status === "error") {
            console.warn("⚠️ Server error:", data.message || data);
            setStatus(`❌ ${data.message || "Грешка от сървъра."}`, "error");
            return;
          }
  
          if (data.status === "exists") {
            setStatus("ℹ️ Файлът вече е удостоверен.", "info");
          } else if (data.status === "success") {
            setStatus("✅ Успешно удостоверено!", "success");
          } else {
            setStatus("❌ Неочакван статус от сървъра.", "error");
            return;
          }
  
          // Populate result
          resultTimestamp.textContent = data.timestamp || "-";
          resultHash.textContent = data.file_hash || "-";
          resultSerial.textContent = data.serial_number || "-";
          resultBlock.classList.remove("hidden");
        } catch (err) {
          console.error("⚠️ JSON parse error:", err);
          console.log("Raw server response:", raw);
          setStatus("❌ Неочакван отговор от сървъра.", "error");
        }
      })
      .catch(err => {
        console.error("❌ Fetch/network error:", err);
        setStatus("❌ Грешка при заявката.", "error");
      });
  }
  
  // Utility: show message with type
  function setStatus(msg, type = "info") {
    const status = document.getElementById("status");
    if (!status) return;
    status.textContent = msg;
    status.className = type; // use .success, .error, .loading, .info for styling
  }
  