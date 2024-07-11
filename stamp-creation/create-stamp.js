document.addEventListener('DOMContentLoaded', function() {
    const hobbyInput = document.getElementById('hobby');
    const fontPreview = document.getElementById('fontPreview');
    const fontInput = document.getElementById('font');
    const savedStamps = document.getElementById('savedStamps');
    const confirmationDialog = document.getElementById('confirmationDialog');
    const finalStamp = document.getElementById('finalStamp');
    const selectedStamp = document.getElementById('selectedStamp');
    const stampSelection = document.getElementById('stampSelection');
    const frequencyType = document.getElementById('frequencyType');
    const frequencyCount = document.getElementById('frequencyCount');
    const frequencyUnit = document.getElementById('frequencyUnit');
    const intermediateGoalType = document.getElementById('intermediateGoalType');
    const intermediateGoalCount = document.getElementById('intermediateGoalCount');
    const intermediateGoalUnit = document.getElementById('intermediateGoalUnit');
    let currentSelectedStamp = null;
    let currentStampId = null;

    const fonts = [
        { name: 'BebasNeue', label: 'Bebas Neue' },
        { name: 'DancingScript', label: 'Dancing Script' },
        { name: 'FredokaOne', label: 'Fredoka One' },
        { name: 'IndieFlower', label: 'Indie Flower' },
        { name: 'Pacifico', label: 'Pacifico' },
        { name: 'PermanentMarker', label: 'Permanent Marker' },
        { name: 'Roboto', label: 'Roboto' },
        { name: 'NotoSansJP', label: 'Noto Sans Japanese' }
    ];

    function createFontOptions() {
        fonts.forEach(font => {
            const div = document.createElement('div');
            div.className = 'font-option';
            div.style.fontFamily = font.label;
            div.textContent = hobbyInput.value || font.label;
            div.dataset.font = font.name;
            fontPreview.appendChild(div);

            div.addEventListener('click', function() {
                document.querySelectorAll('.font-option').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                fontInput.value = this.dataset.font;
            });
        });
    }

    function updateFontPreviews() {
        document.querySelectorAll('.font-option').forEach(div => {
            div.textContent = hobbyInput.value || div.dataset.font;
        });
    }

    function updateFrequencyUnit() {
        switch (frequencyType.value) {
            case 'daily':
                frequencyUnit.textContent = '回/日';
                frequencyCount.max = 24;
                break;
            case 'weekly':
                frequencyUnit.textContent = '回/週';
                frequencyCount.max = 7;
                break;
            case 'monthly':
                frequencyUnit.textContent = '回/月';
                frequencyCount.max = 31;
                break;
        }
        if (parseInt(frequencyCount.value) > parseInt(frequencyCount.max)) {
            frequencyCount.value = frequencyCount.max;
        }
    }

    function updateIntermediateGoalUnit() {
        switch (intermediateGoalType.value) {
            case 'week':
                intermediateGoalUnit.textContent = '週間後';
                intermediateGoalCount.max = 52;
                break;
            case 'month':
                intermediateGoalUnit.textContent = 'ヶ月後';
                intermediateGoalCount.max = 12;
                break;
            case 'year':
                intermediateGoalUnit.textContent = '年後';
                intermediateGoalCount.max = 10;
                break;
        }
        if (parseInt(intermediateGoalCount.value) > parseInt(intermediateGoalCount.max)) {
            intermediateGoalCount.value = intermediateGoalCount.max;
        }
    }

    function updateStampList() {
        fetch('get_stamps.php')
        .then(response => response.json())
        .then(stamps => {
            const latestStampsContainer = document.getElementById('latestStamps').querySelector('.stamp-list');
            const registeredStampsContainer = document.getElementById('registeredStamps').querySelector('.stamp-list');
            
            latestStampsContainer.innerHTML = '';
            registeredStampsContainer.innerHTML = '';
    
            stamps.forEach(stamp => {
                const stampDiv = document.createElement('div');
                stampDiv.className = `saved-stamp ${stamp.status}-stamp`;
                stampDiv.dataset.stampId = stamp.id;
                stampDiv.innerHTML = `
                    <img src="${stamp.image_path}" alt="${stamp.status} Stamp">
                    <p>${stamp.hobby}</p>
                `;
    
                if (stamp.status === 'draft') {
                    latestStampsContainer.appendChild(stampDiv);
                } else if (stamp.status === 'registered') {
                    stampDiv.innerHTML += `
                        <p>開始日: ${stamp.start_date || 'N/A'}</p>
                        <p>頻度: ${stamp.frequency_count || 'N/A'} 回/${stamp.frequency_type || 'N/A'}</p>
                    `;
                    registeredStampsContainer.appendChild(stampDiv);
                }
            });
    
            if (latestStampsContainer.children.length === 0) {
                latestStampsContainer.innerHTML = '<p>最新のスタンプはありません。</p>';
            }
            if (registeredStampsContainer.children.length === 0) {
                registeredStampsContainer.innerHTML = '<p>登録済みスタンプはありません。</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching stamps:', error);
        });
    }

    createFontOptions();

    hobbyInput.addEventListener('input', updateFontPreviews);

    // 初期選択
    document.querySelector('.font-option[data-font="BebasNeue"]').classList.add('selected');

    document.getElementById('savedStamps').addEventListener('click', function(e) {
        const stampElement = e.target.closest('.draft-stamp');
        if (stampElement) {
            currentSelectedStamp = stampElement;
            currentStampId = stampElement.dataset.stampId;
            confirmationDialog.style.display = 'block';
        }
    });

    document.getElementById('confirmYes').addEventListener('click', function() {
        if (currentSelectedStamp) {
            stampSelection.style.display = 'none';
            confirmationDialog.style.display = 'none';
            finalStamp.style.display = 'block';
            selectedStamp.innerHTML = currentSelectedStamp.innerHTML;
        }
    });

    document.getElementById('confirmNo').addEventListener('click', function() {
        confirmationDialog.style.display = 'none';
        currentSelectedStamp = null;
        currentStampId = null;
    });

    frequencyType.addEventListener('change', updateFrequencyUnit);
    intermediateGoalType.addEventListener('change', updateIntermediateGoalUnit);

    // 初期表示時にも単位を設定
    updateFrequencyUnit();
    updateIntermediateGoalUnit();

    // スタンプ作成フォームの送信処理
    document.getElementById('stampForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('save_stamp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                return response.json();
            }
        })
        .then(result => {
            if (result && !result.success) {
                alert('エラー: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('スタンプ作成中にエラーが発生しました。');
        });
    });

    // 追加情報フォームの送信処理
    document.getElementById('additionalInfoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.stamp_id = currentStampId;

        fetch('save_stamp_usage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('習い事の情報が保存されました。');
                updateStampList();
                finalStamp.style.display = 'none';
                stampSelection.style.display = 'block';
            } else {
                alert('エラー: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存中にエラーが発生しました。');
        });
    });

    // 初期表示時にスタンプリストを更新
    updateStampList();
});