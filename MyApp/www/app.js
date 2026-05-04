const API_URL = `127.0.0.1:8001/api`;
const DOCTORS_URL =
    'https://data.issy.com/api/explore/v2.1/catalog/datasets/medecins-generalistes-et-infirmiers/records?limit=100&refine=specialite%3A%22MEDECIN%20GENERALISTE%22';
const statusMessage = document.getElementById('statusMessage');
const authSection = document.getElementById('authSection');
const guestInfoCard = document.getElementById('guestInfoCard');
const loginCard = document.getElementById('loginCard');
const registerCard = document.getElementById('registerCard');
const bookingCard = document.getElementById('bookingCard');
const rdvCard = document.getElementById('rdvCard');
const rdvList = document.getElementById('rdvList');
const doctorSelect = document.getElementById('doctorSelect');
const bookingTitle = document.getElementById('bookingTitle');
const cancelEditButton = document.getElementById('cancelEditButton');
const rdvDate = document.getElementById('rdvDate');
const rdvTime = document.getElementById('rdvTime');
const authStateLabel = document.getElementById('authStateLabel');
const authStateCopy = document.getElementById('authStateCopy');
const heroLead = document.getElementById('heroLead');
const doctorCountValue = document.getElementById('doctorCountValue');
const appointmentCountValue = document.getElementById('appointmentCountValue');
const nextAppointmentValue = document.getElementById('nextAppointmentValue');
const heroLoginButton = document.getElementById('heroLoginButton');
const heroRegisterButton = document.getElementById('heroRegisterButton');
const heroBookingButton = document.getElementById('heroBookingButton');
const heroLogoutButton = document.getElementById('heroLogoutButton');

let editingRdvId = null;
let doctors = [];
let map;
let selectedTimeValue = null;
let currentPatient = null;

function showMessage(message, isError = false) {
    statusMessage.textContent = message;
    statusMessage.classList.remove('hidden', 'error');

    if (isError) {
        statusMessage.classList.add('error');
    }
}

function clearMessage() {
    statusMessage.textContent = '';
    statusMessage.classList.add('hidden');
    statusMessage.classList.remove('error');
}

function setToken(token) {
    document.cookie = `token=${token}; path=/; SameSite=Lax`;
}

function getToken() {
    const match = document.cookie.match(new RegExp('(^| )token=([^;]+)'));
    return match ? match[2] : null;
}

function clearToken() {
    document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}

function clearRegisterForm() {
    document.getElementById('nomPatient').value = '';
    document.getElementById('prenomPatient').value = '';
    document.getElementById('ruePatient').value = '';
    document.getElementById('cpPatient').value = '';
    document.getElementById('villePatient').value = 'Issy-les-Moulineaux';
    document.getElementById('telPatient').value = '';
    document.getElementById('loginPatientRegister').value = '';
    document.getElementById('mdpPatientRegister').value = '';
}

function parseLocalDateTime(dateTimeString) {
    const [datePart, timePart] = dateTimeString.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute, second] = timePart.split(':').map(Number);
    return new Date(year, month - 1, day, hour, minute, second || 0);
}

function formatAppointmentDateTime(dateTimeString) {
    return parseLocalDateTime(dateTimeString).toLocaleString('fr-FR');
}

function updateHeroState(isAuthenticated) {
    document.body.classList.toggle('is-authenticated', isAuthenticated);
    heroLoginButton.classList.toggle('hidden', isAuthenticated);
    heroRegisterButton.classList.toggle('hidden', isAuthenticated);
    heroBookingButton.classList.toggle('hidden', !isAuthenticated);
    heroLogoutButton.classList.toggle('hidden', !isAuthenticated);

    if (isAuthenticated && currentPatient) {
        authStateLabel.textContent = 'Espace actif';
        authStateCopy.textContent = `${currentPatient.prenomPatient} ${currentPatient.nomPatient} peut gerer ses rendez-vous en ligne.`;
        heroLead.textContent = 'Votre espace patient est pret: consultez la carte, reservez un creneau et suivez vos rendez-vous a venir.';
        return;
    }

    authStateLabel.textContent = 'Visiteur';
    authStateCopy.textContent = 'Connectez-vous ou creez votre espace patient pour acceder a la prise de rendez-vous.';
    heroLead.textContent = 'Recherchez un praticien de proximite, connectez-vous puis reservez un creneau de 20 minutes.';
}

function setAuthView(view = null) {
    const isVisible = view === 'login' || view === 'register';

    authSection.classList.toggle('hidden', !isVisible);
    loginCard.classList.toggle('hidden', view !== 'login');
    registerCard.classList.toggle('hidden', view !== 'register');
}

function updateDoctorMetric() {
    doctorCountValue.textContent = doctors.length > 0 ? String(doctors.length) : '--';
}

function updateAppointmentMetrics(rdvs = []) {
    appointmentCountValue.textContent = String(rdvs.length);

    if (rdvs.length === 0) {
        nextAppointmentValue.textContent = 'Aucun programme';
        return;
    }

    const sorted = [...rdvs].sort(
        (first, second) => parseLocalDateTime(first.dateHeureRdv).getTime() - parseLocalDateTime(second.dateHeureRdv).getTime(),
    );
    const nextRdv = sorted[0];
    nextAppointmentValue.textContent = `${nextRdv.prenomMedecin} ${nextRdv.nomMedecin} - ${formatAppointmentDateTime(nextRdv.dateHeureRdv)}`;
}

function initializeTimeOptions(disabledSlots = [], preferredTime = null) {
    rdvTime.innerHTML = '';
    const disabledSet = new Set(disabledSlots);
    let firstAvailableValue = null;

    for (let minutes = 8 * 60; minutes <= 19 * 60; minutes += 20) {
        if (minutes >= 12 * 60 && minutes < 14 * 60) {
            continue;
        }

        const hh = String(Math.floor(minutes / 60)).padStart(2, '0');
        const mm = String(minutes % 60).padStart(2, '0');
        const value = `${hh}:${mm}`;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'time-button';
        button.textContent = value;
        button.dataset.value = value;
        button.disabled = disabledSet.has(value);

        if (button.disabled) {
            button.textContent = `${value} indisponible`;
        }

        button.addEventListener('click', () => {
            if (button.disabled) {
                return;
            }

            selectedTimeValue = value;
            highlightSelectedTime();
        });

        rdvTime.appendChild(button);

        if (!button.disabled && !firstAvailableValue) {
            firstAvailableValue = value;
        }
    }

    const nextValue = preferredTime && !disabledSet.has(preferredTime) ? preferredTime : firstAvailableValue;
    selectedTimeValue = nextValue;
    highlightSelectedTime();
}

function highlightSelectedTime() {
    rdvTime.querySelectorAll('.time-button').forEach((button) => {
        button.classList.toggle('selected', button.dataset.value === selectedTimeValue);
    });
}

function setDefaultBookingDate() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    rdvDate.value = tomorrow.toISOString().split('T')[0];
}

function buildRdvDateTimeValue() {
    if (!rdvDate.value || !selectedTimeValue) {
        return '';
    }

    return `${rdvDate.value} ${selectedTimeValue}:00`;
}

async function refreshAvailableSlots(preferredTime = null) {
    const selectedDoctor = getDoctorIdentity(doctorSelect.value);

    if (!selectedDoctor || !rdvDate.value || !getToken()) {
        initializeTimeOptions([], preferredTime);
        return;
    }

    try {
        const params = new URLSearchParams({
            idMedecin: selectedDoctor.idMedecin,
            date: rdvDate.value,
        });

        if (editingRdvId !== null) {
            params.set('excludeRdvId', String(editingRdvId));
        }

        const response = await apiFetch(`/rdv/unavailable-slots?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await parseJsonResponse(response);
        initializeTimeOptions(data.slots || [], preferredTime);
    } catch {
        initializeTimeOptions([], preferredTime);
    }
}

function fillBookingFieldsFromDateTime(dateTimeString) {
    const date = parseLocalDateTime(dateTimeString);
    rdvDate.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const timeValue = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    refreshAvailableSlots(timeValue);
}

async function parseJsonResponse(response) {
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = data.errors ? Object.values(data.errors).flat().join('\n') : null;
        throw new Error(errors || data.message || 'Une erreur est survenue.');
    }

    return data;
}

async function apiFetch(path, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return fetch(`${API_URL}${path}`, {
        ...options,
        headers,
    });
}

function getDoctorIdentity(value) {
    return doctors.find((entry) => entry.idMedecin === value) || null;
}

function scrollToSection(sectionId) {
    if (sectionId === 'loginCard') {
        setAuthView('login');
    }

    if (sectionId === 'registerCard') {
        setAuthView('register');
    }

    const section = document.getElementById(sectionId);

    if (!section || section.classList.contains('hidden')) {
        return;
    }

    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function updateAuthenticatedUi(isAuthenticated) {
    if (isAuthenticated) {
        setAuthView(null);
    }

    guestInfoCard.classList.toggle('hidden', isAuthenticated);
    bookingCard.classList.toggle('hidden', !isAuthenticated);
    rdvCard.classList.toggle('hidden', !isAuthenticated);
    updateHeroState(isAuthenticated);
}

async function login() {
    clearMessage();

    try {
        const response = await apiFetch('/login', {
            method: 'POST',
            body: JSON.stringify({
                loginPatient: document.getElementById('loginPatient').value.trim(),
                mdpPatient: document.getElementById('mdpPatientLogin').value,
            }),
        });

        const data = await parseJsonResponse(response);
        setToken(data.token);
        showMessage('Connexion reussie.');
        await hydrateAuthenticatedExperience();
    } catch (error) {
        clearToken();
        updateAuthenticatedUi(false);
        showMessage(error.message, true);
    }
}

async function register() {
    clearMessage();

    try {
        const response = await apiFetch('/register', {
            method: 'POST',
            body: JSON.stringify({
                nomPatient: document.getElementById('nomPatient').value.trim(),
                prenomPatient: document.getElementById('prenomPatient').value.trim(),
                ruePatient: document.getElementById('ruePatient').value.trim(),
                cpPatient: document.getElementById('cpPatient').value.trim(),
                villePatient: document.getElementById('villePatient').value.trim(),
                telPatient: document.getElementById('telPatient').value.trim(),
                loginPatient: document.getElementById('loginPatientRegister').value.trim(),
                mdpPatient: document.getElementById('mdpPatientRegister').value,
            }),
        });

        const data = await parseJsonResponse(response);
        setToken(data.token);
        clearRegisterForm();
        showMessage('Inscription reussie.');
        await hydrateAuthenticatedExperience();
    } catch (error) {
        clearToken();
        updateAuthenticatedUi(false);
        showMessage(error.message, true);
    }
}

async function loadProfile() {
    const response = await apiFetch('/profile');
    const data = await parseJsonResponse(response);
    currentPatient = data.patient;
    updateHeroState(true);
}

function createAppointmentItem(rdv) {
    const item = document.createElement('li');
    item.className = 'appointment-item';

    const details = document.createElement('div');
    const title = document.createElement('p');
    const when = document.createElement('p');
    title.className = 'appointment-title';
    title.textContent = `Dr ${rdv.prenomMedecin} ${rdv.nomMedecin}`;
    when.textContent = formatAppointmentDateTime(rdv.dateHeureRdv);
    details.append(title, when);

    const actions = document.createElement('div');
    actions.className = 'appointment-actions';

    const editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.className = 'secondary-button';
    editButton.textContent = 'Modifier';
    editButton.addEventListener('click', () => startEditRdv(rdv));

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'danger-button';
    deleteButton.textContent = 'Annuler';
    deleteButton.addEventListener('click', () => deleteRdv(rdv.idRdv));

    actions.append(editButton, deleteButton);
    item.append(details, actions);

    return item;
}

async function loadRdv() {
    const response = await apiFetch('/rdv');
    const rdvs = await parseJsonResponse(response);
    updateAppointmentMetrics(rdvs);
    rdvList.innerHTML = '';

    if (rdvs.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'empty-state';
        empty.textContent = 'Aucun rendez-vous a venir pour le moment.';
        rdvList.appendChild(empty);
        return;
    }

    rdvs.forEach((rdv) => {
        rdvList.appendChild(createAppointmentItem(rdv));
    });
}

function startEditRdv(rdv) {
    editingRdvId = rdv.idRdv;
    bookingTitle.textContent = 'Modifier un rendez-vous';
    cancelEditButton.classList.remove('hidden');
    doctorSelect.value = rdv.idMedecin;
    fillBookingFieldsFromDateTime(rdv.dateHeureRdv);
    bookingCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetBookingForm() {
    editingRdvId = null;
    bookingTitle.textContent = 'Reserver un rendez-vous';
    cancelEditButton.classList.add('hidden');
    if (doctorSelect.options.length > 0) {
        doctorSelect.selectedIndex = 0;
    }
    setDefaultBookingDate();
    initializeTimeOptions();
    refreshAvailableSlots();
}

async function submitRdv() {
    clearMessage();

    try {
        const selectedDoctor = getDoctorIdentity(doctorSelect.value);

        if (!selectedDoctor) {
            throw new Error('Veuillez choisir un medecin.');
        }

        if (!selectedTimeValue) {
            throw new Error('Veuillez choisir un horaire disponible.');
        }

        const dateHeureRdv = buildRdvDateTimeValue();

        if (!dateHeureRdv) {
            throw new Error('Veuillez choisir un jour et une heure.');
        }

        const payload = {
            nomMedecin: selectedDoctor.nomMedecin,
            prenomMedecin: selectedDoctor.prenomMedecin,
            idMedecin: selectedDoctor.idMedecin,
            dateHeureRdv,
        };

        const isEditing = editingRdvId !== null;
        const response = await apiFetch(isEditing ? `/rdv/${editingRdvId}` : '/rdv', {
            method: isEditing ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });

        await parseJsonResponse(response);
        showMessage(isEditing ? 'Rendez-vous modifie.' : 'Rendez-vous cree.');
        resetBookingForm();
        await loadRdv();
    } catch (error) {
        showMessage(error.message, true);
    }
}

async function deleteRdv(idRdv) {
    clearMessage();

    try {
        const response = await apiFetch(`/rdv/${idRdv}`, {
            method: 'DELETE',
        });

        await parseJsonResponse(response);
        showMessage('Rendez-vous annule.');
        await loadRdv();
        await refreshAvailableSlots();
    } catch (error) {
        showMessage(error.message, true);
    }
}

function logout() {
    clearToken();
    currentPatient = null;
    updateAuthenticatedUi(false);
    setAuthView(null);
    rdvList.innerHTML = '';
    updateAppointmentMetrics([]);
    resetBookingForm();
    showMessage('Vous etes deconnecte.');
}

function populateDoctorSelect() {
    doctorSelect.innerHTML = '';

    if (doctors.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Aucun medecin charge';
        doctorSelect.appendChild(option);
        return;
    }

    doctors.forEach((doctor) => {
        const option = document.createElement('option');
        option.value = doctor.idMedecin;
        option.textContent = `Dr ${doctor.prenomMedecin} ${doctor.nomMedecin}`;
        doctorSelect.appendChild(option);
    });
}

function attachPopupBookingButton(doctor) {
    setTimeout(() => {
        const button = document.getElementById(`book-${doctor.idMedecin}`);

        if (!button) {
            return;
        }

        button.addEventListener('click', () => {
            if (!getToken()) {
                showMessage("Connectez-vous d'abord pour reserver un rendez-vous.", true);
                setAuthView('login');
                document.getElementById('loginCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            clearMessage();
            focusBookingForDoctor(doctor.idMedecin);
        });
    }, 0);
}

function focusBookingForDoctor(doctorId) {
    doctorSelect.value = doctorId;
    bookingCard.classList.remove('hidden');
    refreshAvailableSlots(selectedTimeValue);
    bookingCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadDoctors() {
    const response = await fetch(DOCTORS_URL);
    const payload = await response.json();

    doctors = (payload.results || [])
        .map((med) => {
            const position = med.geolocalisation || med.geo_point_2d;

            if (!position) {
                return null;
            }

            return {
                idMedecin: med.recordid || `${med.nom || ''}-${med.prenom || ''}`,
                nomMedecin: (med.nom || '').trim(),
                prenomMedecin: (med.prenom || '').trim(),
                adresse: med.adresse || '',
                latitude: Number(position.lat),
                longitude: Number(position.lon),
            };
        })
        .filter((doctor) => doctor && doctor.nomMedecin);

    populateDoctorSelect();
    updateDoctorMetric();
    refreshAvailableSlots();

    doctors.forEach((doctor) => {
        const marker = L.marker([doctor.latitude, doctor.longitude]).addTo(map);
        marker.bindPopup(
            `<b>Dr ${doctor.prenomMedecin} ${doctor.nomMedecin}</b><br>${doctor.adresse || ''}<br><button type="button" class="popup-book-button" id="book-${doctor.idMedecin}">Prendre rendez-vous</button>`,
        );
        marker.on('popupopen', () => attachPopupBookingButton(doctor));
    });
}

async function hydrateAuthenticatedExperience() {
    updateAuthenticatedUi(true);
    await Promise.all([loadProfile(), loadRdv(), refreshAvailableSlots()]);
}

doctorSelect.addEventListener('change', () => {
    refreshAvailableSlots();
});

rdvDate.addEventListener('change', () => {
    refreshAvailableSlots();
});

initializeTimeOptions();
setDefaultBookingDate();

map = L.map('map').setView([48.8245, 2.2746], 13);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).addTo(map);

loadDoctors().catch(() => {
    populateDoctorSelect();
    updateDoctorMetric();
    showMessage("Impossible de charger les medecins depuis l'API distante.", true);
});

updateAuthenticatedUi(false);
updateDoctorMetric();
updateAppointmentMetrics([]);

if (getToken()) {
    hydrateAuthenticatedExperience().catch(() => {
        clearToken();
        currentPatient = null;
        updateAuthenticatedUi(false);
        updateAppointmentMetrics([]);
        showMessage('La session a expire ou le token ne correspond plus a votre adresse IP.', true);
    });
}
