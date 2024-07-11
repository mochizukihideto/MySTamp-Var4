document.addEventListener('DOMContentLoaded', function() {
    const stampsContainer = document.getElementById('stamps-container');
    const popup = document.getElementById('encouragementPopup');
    const closeBtn = document.querySelector('.close');
    const encouragementImage = document.getElementById('encouragementImage');
    const encouragementMessage = document.getElementById('encouragementMessage');

    stampsContainer.addEventListener('click', function(e) {
        const stampElement = e.target.closest('.stamp');
        if (stampElement) {
            const stampId = stampElement.dataset.stampId;
            const message = stampElement.dataset.encouragementMessage;
            const imagePath = stampElement.dataset.encouragementImage;

            updateStampUsage(stampId);

            // ポップアップの内容を設定
            if (imagePath && imagePath !== "undefined") {
                encouragementImage.src = imagePath;
                encouragementImage.style.display = 'block';
            } else {
                encouragementImage.style.display = 'none';
            }
            encouragementMessage.textContent = message || "がんばりましたね！";

            // ポップアップを表示
            popup.style.display = 'block';
        }
    });

    // ポップアップを閉じる
    closeBtn.onclick = function() {
        popup.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == popup) {
            popup.style.display = 'none';
        }
    }
});

function updateStampUsage(stampId) {
    fetch('../api/use_stamp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ stamp_id: stampId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Stamp usage updated successfully');
            // 残り日数を更新
            const stampElement = document.querySelector(`.stamp[data-stamp-id="${stampId}"]`);
            if (stampElement && data.days_left !== undefined) {
                const daysLeftElement = stampElement.querySelector('p:last-child');
                if (daysLeftElement) {
                    daysLeftElement.textContent = `今度の目標達成まであと${data.days_left}日`;
                }
            }
        } else {
            console.error('Error updating stamp usage:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}