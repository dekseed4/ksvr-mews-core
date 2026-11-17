document.addEventListener('DOMContentLoaded', function() {
    // 🌟 ดักไว้ก่อน: ถ้าหน้านั้นไม่มี mews-form ให้หยุดการทำงานทันที ป้องกัน Error
    const mewsForm = document.getElementById('mews-form');
    if (!mewsForm) return;

    // ตรวจสอบว่าหน้าเว็บนี้มีการส่งกฎเกณฑ์ (mewsRules) มาจาก Blade หรือไม่
    if (typeof mewsRules === 'undefined') {
        console.warn('MEWS Engine: ไม่พบข้อมูลเกณฑ์ประเมิน (mewsRules) ระบบอาจไม่สามารถคำนวณคะแนนได้');
    }

    const inputs = document.querySelectorAll('.mews-input');
    const emergencyCheckboxes = document.querySelectorAll('input[name="emergency_conditions[]"]');
    const badge = document.getElementById('mews-badge');
    const scoreDisplay = document.getElementById('mews-score-display');
    const statusText = document.getElementById('mews-status-text');
    const emergencyAlertBox = document.getElementById('emergency-alert-box'); 
    const emergencyAlertList = document.getElementById('emergency-alert-list');

    // 🌟 ลอจิกการดึงคะแนนแบบ Dynamic (อ่านค่าจาก Database ไม่ฝังตัวเลขในโค้ด)
    function getScore(paramKey, value) {
        // 1. ถ้ายังไม่ได้กรอกค่า ให้รีเทิร์น null เพื่อแจ้งเตือนว่าข้อมูลไม่ครบ
        if (!value || value === '') return null;

        // 2. ถ้าใน Database ไม่มีการตั้งกฎสำหรับค่านี้ ให้ได้ 0 แต้ม 
        if (!mewsRules || !mewsRules[paramKey] || mewsRules[paramKey].length === 0) {
            return 0; 
        }

        const rules = mewsRules[paramKey];

        if (paramKey === 'avpu') {
            const matchedRule = rules.find(r => r.value.toUpperCase() === value.toUpperCase());
            if (!matchedRule) console.error(`MEWS Error: ไม่พบเกณฑ์ AVPU สำหรับค่า "${value}" ใน Database`);
            return matchedRule ? matchedRule.score : null;
        } else {
            const numValue = parseFloat(value);
            if (isNaN(numValue)) return null;

            // 🌟 3. หาช่วงที่ตรงกัน (Min-Max) ตามปกติ
            let matchedRule = rules.find(r => numValue >= r.min && numValue <= r.max);
            
            // 🌟 4. ระบบสำรอง (Fallback): ถ้าหาไม่เจอ แปลว่ากรอกตัวเลขเกินขอบเขต (Out of Range)
            if (!matchedRule && rules.length > 0) {
                // โคลนและเรียงลำดับกฎจากค่าน้อยไปมาก
                const sortedRules = [...rules].sort((a, b) => parseFloat(a.min) - parseFloat(b.min));
                
                const minBoundaryRule = sortedRules[0]; // ช่วงที่ต่ำที่สุด
                const maxBoundaryRule = sortedRules[sortedRules.length - 1]; // ช่วงที่สูงที่สุด

                if (numValue < parseFloat(minBoundaryRule.min)) {
                    // ถ้าน้อยกว่าขอบเขตล่างสุด ให้ปัดไปใช้คะแนนของช่วงต่ำสุด
                    matchedRule = minBoundaryRule;
                    console.warn(`MEWS Warning: ค่า ${paramKey} ต่ำกว่าเกณฑ์ (${numValue} < ${minBoundaryRule.min}) ปัดคะแนนเป็น ${matchedRule.score}`);
                } else if (numValue > parseFloat(maxBoundaryRule.max)) {
                    // ถ้ามากกว่าขอบเขตบนสุด ให้ปัดไปใช้คะแนนของช่วงสูงสุด (เช่น O2Sat 120 > 100)
                    matchedRule = maxBoundaryRule;
                    console.warn(`MEWS Warning: ค่า ${paramKey} สูงกว่าเกณฑ์ (${numValue} > ${maxBoundaryRule.max}) ปัดคะแนนเป็น ${matchedRule.score}`);
                }
            }

            return matchedRule ? matchedRule.score : null;
        }
    }

    // 🌟 ฟังก์ชันหลักอัปเดตหน้าจอ
    function updateUI() {
        const sbpVal = document.getElementById('sbp').value;
        const hrVal = document.getElementById('hr').value;
        const rrVal = document.getElementById('rr').value;
        const tempVal = document.getElementById('temp').value;
        const o2satVal = document.getElementById('o2sat').value;
        const avpuVal = document.getElementById('avpu').value;

        const checkedEmergencies = document.querySelectorAll('input[name="emergency_conditions[]"]:checked');
        const hasEmergency = checkedEmergencies.length > 0;

        // -----------------------------------------------------
        // 1. จัดการแสดงผล UI กล่องอาการฉุกเฉินเฉพาะโรค (โค้ดดั้งเดิมของคุณศุภกิจ)
        // -----------------------------------------------------
        if (hasEmergency) {
            emergencyAlertBox.classList.remove('hidden');
            document.getElementById('emergency-alert-text').innerText = `พบอาการฉุกเฉิน (${checkedEmergencies.length} อาการ):`;
            emergencyAlertList.innerHTML = '';

            function getCategoryName(val) {
                if (val.startsWith('mi_')) return 'MI (กล้ามเนื้อหัวใจขาดเลือด)';
                if (val.startsWith('stroke_')) return 'Stroke (หลอดเลือดสมอง)';
                if (val.startsWith('heat_')) return 'Heat Injury (โรคลมร้อน)';
                if (val.startsWith('ana_')) return 'Anaphylaxis (แพ้รุนแรง)';
                if (val === 'appendicitis') return 'Appendicitis';
                if (val.startsWith('sepsis_')) return 'Sepsis (ติดเชื้อในเลือด)';
                if (val === 'trauma') return 'Trauma (อุบัติเหตุ)';
                return 'อาการฉุกเฉิน';
            }
            
            const groupedConditions = {};
            checkedEmergencies.forEach(cb => {
                const labelText = cb.nextElementSibling ? cb.nextElementSibling.textContent.trim() : cb.value;
                const categoryName = getCategoryName(cb.value);
                if (!groupedConditions[categoryName]) {
                    groupedConditions[categoryName] = [];
                }
                groupedConditions[categoryName].push(labelText);
            });

            for (const [category, symptoms] of Object.entries(groupedConditions)) {
                const li = document.createElement('li');
                li.className = "mb-2 list-none";
                li.innerHTML = `<span class="font-bold text-red-900 border-b border-red-200 pb-0.5"><i class="fa-solid fa-caret-right mr-1"></i> ${category}</span>`;
                
                const subUl = document.createElement('ul');
                subUl.className = "list-disc list-inside ml-5 mt-1 text-red-700 space-y-0.5 text-xs font-normal";
                symptoms.forEach(sym => {
                    subUl.innerHTML += `<li>${sym}</li>`;
                });
                li.appendChild(subUl);
                emergencyAlertList.appendChild(li);
            }
        } else {
            emergencyAlertBox.classList.add('hidden');
            emergencyAlertList.innerHTML = '';
        }

        // -----------------------------------------------------
        // 2. คำนวณคะแนน MEWS ผ่าน Dynamic Engine
        // -----------------------------------------------------
        const scores = {
            sbp: getScore('sbp', sbpVal),
            hr: getScore('hr', hrVal),
            rr: getScore('rr', rrVal),
            temp: getScore('temp', tempVal),
            o2sat: getScore('o2sat', o2satVal),
            avpu: getScore('avpu', avpuVal)
        };

        // ตรวจสอบว่ากรอกครบทุกช่องไหม และมีค่าไหนวิกฤต (ได้ 3 คะแนน) หรือไม่
        let isComplete = true;
        let totalScore = 0;
        let isCritical = false;

        for (const [key, score] of Object.entries(scores)) {
            if (score === null) {
                isComplete = false;
            } else {
                totalScore += score;
                if (score === 3) isCritical = true; // ตามหลัก MEWS ถ้ามีค่าใดได้ 3 ถือว่าฉุกเฉิน
            }
        }

        // -----------------------------------------------------
        // 3. แสดงผลคะแนนและสีของวงกลม (UI Status) อัปเกรดตามมาตรฐาน KSVR
        // -----------------------------------------------------
        if (!isComplete) {
            // ... (โค้ดตอนกรอกไม่ครบ ปล่อยไว้เหมือนเดิมครับ) ...
            scoreDisplay.innerText = "-";
            document.getElementById('hidden_total_score').value = "";
            document.getElementById('hidden_risk_level').value = "";

            if (hasEmergency) {
                badge.className = "w-32 h-32 mx-auto rounded-full bg-red-500 border-8 border-red-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-lg animate-pulse";
                scoreDisplay.className = "text-5xl font-black text-white";
                statusText.innerHTML = `<span class="text-red-600 font-bold">รอประเมินสัญญาณชีพ</span>`;
            } else {
                badge.className = "w-32 h-32 mx-auto rounded-full bg-slate-100 border-4 border-slate-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-inner";
                scoreDisplay.className = "text-5xl font-black text-slate-400";
                statusText.innerHTML = "กรุณากรอกข้อมูลสัญญาณชีพให้ครบถ้วน";
            }
            return;
        }

        // กรณีข้อมูลครบถ้วน
        scoreDisplay.innerText = totalScore;
        document.getElementById('hidden_total_score').value = totalScore;

        // 🚨 1. ฉุกเฉินวิกฤต (สีแดง / Score 3+) -> ส่ง ER
        if (isCritical || totalScore >= 3 || hasEmergency) { 
            badge.className = "w-32 h-32 mx-auto rounded-full bg-rose-500 border-8 border-rose-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-lg animate-pulse";
            scoreDisplay.className = "text-5xl font-black text-white";
            statusText.innerHTML = `<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> ฉุกเฉิน! รายงานแพทย์เวรทันที (เข้า ER)</span>`;
            document.getElementById('hidden_risk_level').value = 'emergency';
            
        // 🟠 2. เฝ้าระวังระดับ 2 (สีส้ม / Score 2 หรือข้อยกเว้น) -> ส่ง ER
        // เงื่อนไข: คะแนนรวม 2 ขึ้นไป "หรือ" คะแนน 1 แต่มาจาก HR(ชีพจร) หรือ RR(การหายใจ)
        } else if (totalScore >= 2 || (totalScore === 1 && (scores.hr > 0 || scores.rr > 0))) { 
            badge.className = "w-32 h-32 mx-auto rounded-full bg-orange-500 border-8 border-orange-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-lg";
            scoreDisplay.className = "text-5xl font-black text-white";
            
            let alertMsg = 'เฝ้าระวังอาการ (เข้า ER)';
            if (totalScore === 1) alertMsg = 'เฝ้าระวังอาการ (คะแนนจากชีพจร/การหายใจ -> เข้า ER)';
            
            statusText.innerHTML = `<span class="text-orange-600 font-bold"><i class="fa-solid fa-bell"></i> ${alertMsg}</span>`;
            document.getElementById('hidden_risk_level').value = 'observe';
            
        // 🟡 3. มีค่าเบี่ยงเบน (สีเหลือง / Score 1) -> ส่ง OPD
        // เงื่อนไข: คะแนน 1 ที่มาจากความดัน (SBP), อุณหภูมิ (Temp), ออกซิเจน (O2), รู้สึกตัว (AVPU)
        } else if (totalScore === 1) { 
            badge.className = "w-32 h-32 mx-auto rounded-full bg-amber-400 border-8 border-amber-100 flex items-center justify-center transition-all duration-500 mb-4 shadow-lg";
            scoreDisplay.className = "text-5xl font-black text-white";
            statusText.innerHTML = `<span class="text-amber-600 font-bold"><i class="fa-solid fa-circle-exclamation"></i> ส่งตรวจ OPD / สัญญาณชีพมีค่าเบี่ยงเบน</span>`;
            
            // 🌟 แม้จะเป็นสีเหลือง แต่แอบส่งค่า normal ไปให้ Backend เพื่อให้เข้าระบบคิว OPD
            document.getElementById('hidden_risk_level').value = 'normal';

        // 🟢 4. ปกติสมบูรณ์ (สีเขียว / Score 0) -> ส่ง OPD
        } else { 
            badge.className = "w-32 h-32 mx-auto rounded-full bg-emerald-500 border-8 border-emerald-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-lg";
            scoreDisplay.className = "text-5xl font-black text-white";
            statusText.innerHTML = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-check-circle"></i> ส่งตรวจ OPD / สัญญาณชีพปกติ</span>`;
            document.getElementById('hidden_risk_level').value = 'normal';
        }
    }

    // 🌟 ติดตั้ง Event Listener ให้กับ Input ทุกตัว
    inputs.forEach(input => {
        input.addEventListener('input', updateUI);
        input.addEventListener('change', updateUI);
    });
    emergencyCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateUI);
    });

    // เรียกทำงานครั้งแรกตอนโหลดหน้า (เผื่อหน้า Edit)
    updateUI(); 

    // -----------------------------------------------------
    // 🛡️ ระบบป้องกันหน้าบ้าน (Standardized UX & Double-Submit Protection)
    // -----------------------------------------------------
    mewsForm.addEventListener('submit', function(e) {
        updateUI(); // รันอัปเดตค่าล่าสุดเข้า hidden input ทันที
        const totalScoreVal = document.getElementById('hidden_total_score').value;
        
        // 1. เช็คข้อมูลไม่ครบ
        if (totalScoreVal === "" || isNaN(totalScoreVal)) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณากรอกข้อมูลสัญญาณชีพให้ครบทุกช่องก่อนบันทึกการประเมินครับ',
                    confirmButtonColor: '#10b981',
                    customClass: { popup: 'rounded-3xl' }
                });
            } else {
                alert('กรุณากรอกข้อมูลสัญญาณชีพให้ครบทุกช่องก่อนบันทึกการประเมินครับ');
            }
            return; // หยุดการทำงาน
        }

        // 2. ป้องกันการกดปุ่มซ้ำซ้อน (Double-Submit Protection)
        const submitBtn = mewsForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึกข้อมูล...';
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
    });
});