import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTheme } from '../context/ThemeContext'
import logo from '../assets/V-MAS Logo.svg'
import { vehiclePassAPI } from '../services/api'
import {
  FileText, CheckCircle, Clock, XCircle, Search, Copy,
  Printer, ArrowLeft, Sun, Moon, ShieldCheck, AlertCircle,
  Truck, Check, Ticket, RefreshCw, Car, Send, RotateCcw
} from 'lucide-react'
import './VehiclePassPage.css'

// ── Translation Dictionaries provided by SUSL ──────────────────────────────
const T3 = {
  si: {
    ref: "SUSL/GA/TD/03",
    uni: "ශ්‍රී ලංකා සබරගමුව විශ්වවිද්‍යාලය",
    uniEn: "Sabaragamuwa University of Sri Lanka",
    title: "කාර්යය මණ්ඩල වාහන හඳුනාගැනීමේ පත්‍රිකා අයදුම් කිරීම",
    s1: "පුද්ගලික තොරතුරු",
    name: "අයදුම්කරුගේ නම",
    desig: "තනතුර",
    dept: "පීඨය / දෙපාර්තමේන්තුව / අංශය",
    status: "පත්වීමේ තත්ත්වය",
    perm: "ස්ථිර",
    temp: "තාවකාලික",
    nic: "ජා.හැ. අංකය",
    mob: "ජංගම දුරකථන අංකය",
    s2: "වාහනයේ විස්තර",
    owner: "අයිතිකරුගේ නම",
    vtype: "වර්ගය",
    reg: "ලියාපදිංචි අංකය",
    eng: "එන්ජින් අංකය",
    chs: "චැසි අංකය",
    n1: "වාහන ලියාපදිංචි පොතේ පිටපතක් අමුණා තිබිය යුතුය.",
    n2: "වාහනය අයදුම්කරුගේ නමින් ලියාපදිංචි කර නොමැති නම්, අයදුම්කරුට වාහනය භාවිතා කිරීම සම්බන්ධයෙන් වාහනයේ හිමිකරුගේ කැමැත්ත සහිත ලිපියක් අමුණා තිබිය යුතුය.",
    decl: "ඉහත සඳහන් තොරතුරු සත්‍ය හා නිවැරදි බව මෙයින් ප්‍රකාශ කර සිටින අතර සේවයෙන් විශ්‍රාම යාමේ දී හෝ ඉල්ලා අස්වීමක දී හෝ අදාළ වාහනය විකුණනු ලැබූ අවස්ථාවක දී මෙම හඳුනා ගැනීමේ පත්‍රිකාව නැවත විශ්වවිද්‍යාලයට භාර දෙන බවට තවදුරටත් සහතික වෙමි.",
    agree: "ඉහත ප්‍රකාශය කියවා එයට එකඟ වෙමි.",
    asig: "අයදුම්කරුගේ අත්සන (නම)",
    date: "දිනය",
    off: "නිල ප්‍රයෝජනය සඳහා පමණි",
    h3a: "පීඨාධිපති හෝ අංශ ප්‍රධානගේ නිර්දේශය",
    t3a: "ඉහත නම සඳහන් අයදුම්කරු මාගේ අංශයේ / දෙපාර්තමේන්තුවේ / පීඨයේ සේවය කරනු ලබන අතර මෙම වාහනය විශ්වවිද්‍යාලය තුළ නිරන්තරයෙන් ධාවනය කරන බැවින් හඳුනා ගැනීමේ පත්‍රිකාව නිකුත් කිරීම නිර්දේශ කරමි.",
    hsig: "පීඨාධිපති / අංශ ප්‍රධාන අත්සන",
    h3b: "උපකුලපති / ලේඛකාධිකාරී අනුමැතිය",
    t3b: "ඉහත නම සඳහන් අයදුම්කරු වෙත වාහන හඳුනා ගැනීමේ පත්‍රිකාව නිකුත් කිරීම අනුමත කරමි.",
    vsig: "උපකුලපති / ලේඛකාධිකාරී",
    h3c: "භාර ගැනීම",
    rcv: "වාහනය සඳහා හඳුනා ගැනීමේ පත්‍රිකාව භාර ගත් දිනය",
    submit: "පෝරමය යවන්න (Submit Form)",
    print: "මුද්‍රණය කරන්න (Print Form)",
    reset: "හිස් කරන්න (Clear)",
    ok: "අයදුම්පත සාර්ථකව පද්ධතියට ඇතුළත් විය. ඔබට දැන් මුද්‍රණය කර අත්සන් කර ඉදිරිපත් කළ හැක.",
    bad: "කරුණාකර රතු පාටින් දැක්වෙන හෝ හිස් අවශ්‍ය ක්ෂේත්‍ර නිවැරදිව පුරවන්න."
  },
  en: {
    ref: "SUSL/GA/TD/03",
    uni: "Sabaragamuwa University of Sri Lanka",
    uniEn: "Sabaragamuwa University of Sri Lanka",
    title: "Application for Staff Vehicle Identification Card",
    s1: "Personal Information",
    name: "Name of applicant",
    desig: "Designation",
    dept: "Faculty / Department / Division",
    status: "Appointment status",
    perm: "Permanent",
    temp: "Temporary",
    nic: "NIC number",
    mob: "Mobile number",
    s2: "Vehicle Details",
    owner: "Name of owner",
    vtype: "Type",
    reg: "Registration number",
    eng: "Engine number",
    chs: "Chassis number",
    n1: "A copy of the vehicle registration book must be attached.",
    n2: "If the vehicle is not registered in the applicant's name, a letter from the vehicle owner consenting to the applicant's use of the vehicle must be attached.",
    decl: "I hereby declare that the information given above is true and correct. I further certify that I will return this identification card to the University upon retirement, resignation, or if the vehicle concerned is sold.",
    agree: "I have read and agree to the above declaration.",
    asig: "Signature of applicant (Name)",
    date: "Date",
    off: "For official use only",
    h3a: "Recommendation of the Dean / Head of Division",
    t3a: "The above-named applicant serves in my Division / Department / Faculty, and since this vehicle is regularly driven within the University premises, I recommend that the identification card be issued.",
    hsig: "Signature of Dean / Head of Division",
    h3b: "Approval of the Vice-Chancellor / Registrar",
    t3b: "I approve the issuance of the vehicle identification card to the above-named applicant.",
    vsig: "Vice-Chancellor / Registrar",
    h3c: "Receipt",
    rcv: "Date the vehicle identification card was received",
    submit: "Submit Form",
    print: "Print Form",
    reset: "Clear Form",
    ok: "Application submitted successfully. You can now print, sign, and submit it.",
    bad: "Please fill in all required fields correctly."
  }
}

const T1 = {
  si: {
    ref: "SUSL/GA/TD/01",
    uni: "ශ්‍රී ලංකා සබරගමුව විශ්වවිද්‍යාලය",
    uniEn: "Sabaragamuwa University of Sri Lanka",
    title: "පිටස්තර ගමන් වාහන අවසර පත්‍රය",
    s1: "අයදුම්කරුගේ තොරතුරු",
    name: "අයදුම්කරුගේ නම",
    tel: "දුරකථන අංකය",
    desig: "තනතුර",
    dept: "පීඨය / දෙපාර්තමේන්තුව / අංශය",
    reason: "වාහනය අවශ්‍ය වන කාරණය",
    reasonSub: "(සහායක ලිපි / ලේඛන අමුණා ඇත)",
    pax: "ගමන් කරන සංඛ්‍යාව",
    vt: "අවශ්‍ය වාහන වර්ගය",
    ppl: "ගමන් කරන පුද්ගලයාගේ / පුද්ගලයින්ගේ නම්",
    sn: "අනු අංක",
    pn: "නම",
    place: "ගමන් කරන ස්ථානය / ස්ථාන",
    route: "ගමන් කරන මාර්ගය සහ ගමනාන්තය",
    due: "නිල කාර්ය නියමිත දිනය",
    time: "වේලාව",
    dep: "පිටත්වීමේ දිනය (විශ්වවිද්‍යාලයේ සිට)",
    tdep: "පිටත්වීමේ වේලාව",
    ret: "නැවත පැමිණීමේ දිනය (පිටස්තර ස්ථානයෙන්)",
    tret: "පැමිණීමේ වේලාව",
    decl: "මා/අප විසින් සපයා ඇති ඉහත තොරතුරු සත්‍ය හා නිවැරදි බවත්, විශ්වවිද්‍යාල වාහන භාවිතයට අදාළ උපදෙස් මා දන්නා බවත්, ඒවා පිළිපදින බවත් මෙයින් ප්‍රකාශ කරමි.",
    agree: "ඉහත ප්‍රකාශය කියවා එයට එකඟ වෙමි.",
    asig: "අයදුම්කරුගේ අත්සන (නම)",
    date: "දිනය",
    s2: "නිර්දේශ",
    hod: "දෙපාර්තමේන්තු ප්‍රධානියා",
    dean: "පීඨාධිපති",
    rec: "නිර්දේශ කරමි / නොකරමි",
    yes: "නිර්දේශ කරමි",
    no: "නිර්දේශ නොකරමි",
    sigl: "අත්සන සහ නම",
    s3: "පිළිපැදිය යුතු උපදෙස්",
    i1: "මෙම පිටස්තර ගමන් අවසර පත්‍රය නිසි ලෙස සම්පූර්ණ කර, නිල කාර්යය නියමිත දිනයට වැඩ කරන දින 04කට පෙර සාමාන්‍ය පාලන අංශය වෙත ගෙනවිත් භාර දිය යුතුය.",
    i2: "පීඨ / දෙපාර්තමේන්තු / අංශ මගින් වාහන ඉල්ලුම් කිරීමේ දී පීඨාධිපතිගේ / අධ්‍යයනාංශ ප්‍රධානගේ / අංශ ප්‍රධානගේ නිර්දේශය සහිතව ඉදිරිපත් කළ යුතුය.",
    i3: "අයදුම්පත්‍රයේ ගමන් මාර්ගය හා ගමනාන්තය පැහැදිලිව සඳහන් කළ යුතු අතර, ඒ අනුව පමණක් ප්‍රවාහන පහසුකම් සැපයීම සිදුකරනු ලැබේ.",
    i4: "සාමාන්‍ය පාලන අංශයට ලැබෙන පිළිවෙලට, අයදුම්පත් පූර්ණ අනුමැතිය සඳහා ඉදිරිපත් කරනු ලැබේ.",
    i5: "කල්වේලා ඉදිරිපත් කරන අයදුම්පත් කෙරෙහි ප්‍රමුඛතාවය ලබා දීමට කටයුතු කරනු ලැබේ.",
    i6: "අනුමත වූ ගමනක් අවලංගු කරන්නේ නම් එය දින 03කට පෙර සාමාන්‍ය පාලන අංශය වෙත දැනුම් දිය යුතුය.",
    s4: "කාර්යාල භාවිතය සඳහා පමණි",
    off: "නිල ප්‍රයෝජනය සඳහා පමණි",
    ga1: "(1) සාමාන්‍ය පාලන අංශ ප්‍රධානගේ නිරීක්ෂණය සහ නිර්දේශය",
    obs: "නිරීක්ෂණ සහ නිර්දේශය (නි. / ජෝ. ස. ලේඛකාධිකාරී – සාමාන්‍ය පාලන)",
    ga2: "(2) සාමාන්‍ය පාලන අංශ ප්‍රධානගේ නිර්දේශය මත වාහනය නිකුත් කිරීමට අනුමැතිය",
    appr: "අනුමැතිය",
    ayes: "දෙමි",
    ano: "නොදෙමි",
    vc: "උපකුලපති / ලේඛකාධිකාරී / නි. / ජෝ. ස. ලේඛකාධිකාරී – පාලන",
    iss: "වාහනය නිකුත් කිරීම",
    vno: "අවසර ලබා දී ඇති වාහන අංකය",
    drv: "රියදුරුගේ නම",
    isig: "වාහනය නිකුත් කරන නිලධාරියාගේ අත්සන",
    s5: "ආරක්ෂක අංශය සඳහා",
    gout: "වාහනය විශ්වවිද්‍යාලයෙන් පිටවූ දිනය",
    gin: "වාහනය විශ්වවිද්‍යාලයට නැවත පැමිණි දිනය",
    mil: "මයිල්මීටරය",
    gname: "ආරක්ෂක නිලධාරියාගේ නම",
    submit: "පෝරමය යවන්න (Submit Form)",
    print: "මුද්‍රණය කරන්න (Print Form)",
    reset: "හිස් කරන්න (Clear)",
    ok: "පිටස්තර ගමන් අවසර පත්‍රය සාර්ථකව යොමු විය. දැන් මුද්‍රණය කර ඉදිරිපත් කරන්න.",
    bad: "කරුණාකර අවශ්‍ය සියලු ක්ෂේත්‍ර නිවැරදිව පුරවන්න."
  },
  en: {
    ref: "SUSL/GA/TD/01",
    uni: "Sabaragamuwa University of Sri Lanka",
    uniEn: "Sabaragamuwa University of Sri Lanka",
    title: "Vehicle Gate Pass for Outstation Travel",
    s1: "Applicant Details",
    name: "Name of the applicant",
    tel: "Telephone no.",
    desig: "Designation",
    dept: "Faculty / Department / Division",
    reason: "Reason for requesting the vehicle",
    reasonSub: "(supporting letters / documents attached)",
    pax: "No. of passengers",
    vt: "Type of vehicle required",
    ppl: "Name/s of person/s travelling",
    sn: "No.",
    pn: "Name",
    place: "Place/s travelling",
    route: "Route to the destination",
    due: "Due date of the official duty",
    time: "Time",
    dep: "Date of departure (from University)",
    tdep: "Time of departure",
    ret: "Date of return (from outstation)",
    tret: "Time of return",
    decl: "I hereby declare that the above information provided by me/us is true and correct, and that I am aware of, and shall abide by, the instructions pertaining to the use of University vehicles.",
    agree: "I have read and agree to the above declaration.",
    asig: "Signature of the applicant (Name)",
    date: "Date",
    s2: "Recommendations",
    hod: "Head of the Department",
    dean: "Dean",
    rec: "Recommended / Non-recommended",
    yes: "Recommended",
    no: "Non-recommended",
    sigl: "Signature and name",
    s3: "Instructions to be followed",
    i1: "This outstation gate pass must be duly completed and handed over to the General Administration Division at least 04 working days before the date of the official duty.",
    i2: "Vehicle requests from Faculties / Departments / Divisions must be submitted with the recommendation of the Dean / Head of Department / Head of Division.",
    i3: "The route and destination must be clearly stated in the application, and transport facilities will be provided only accordingly.",
    i4: "Applications will be forwarded for final approval in the order in which they are received by the General Administration Division.",
    i5: "Priority will be given to applications submitted well in advance.",
    i6: "If an approved trip is cancelled, the General Administration Division must be informed at least 03 days before.",
    s4: "For Office Use Only",
    off: "For official use only",
    ga1: "(1) Observations and recommendation of the Head of General Administration",
    obs: "Observations and recommendation (DR/SAR – General Administration)",
    ga2: "(2) Approval to issue the vehicle on the recommendation of the Head of General Administration",
    appr: "Approval",
    ayes: "Approved",
    ano: "Not approved",
    vc: "Vice-Chancellor / Registrar / DR/SAR – General Administration",
    iss: "Vehicle issue",
    vno: "Vehicle number allocated",
    drv: "Driver's name",
    isig: "Signature of the officer issuing the vehicle",
    s5: "For the Security Division",
    gout: "Date vehicle left the University",
    gin: "Date vehicle returned to the University",
    mil: "Milometer",
    gname: "Name of the security officer",
    submit: "Submit Form",
    print: "Print Form",
    reset: "Clear Form",
    ok: "Form is complete. Print it, sign it, and submit it.",
    bad: "Please fill in all required fields."
  }
}

const VehiclePassPage = () => {
  const navigate = useNavigate()
  const { theme, setTheme } = useTheme()
  const isLight = theme === 'light'

  // Language state (si / en)
  const [lang, setLangState] = useState(() => {
    try { return localStorage.getItem('susl-lang') || 'si' } catch (e) { return 'si' }
  })
  const setLang = (l) => {
    setLangState(l)
    try { localStorage.setItem('susl-lang', l) } catch (e) {}
  }

  // Active View Tab: 'form03' (Staff Vehicle ID), 'form01' (Outstation Gate Pass), 'check' (Track Status)
  const [activeTab, setActiveTab] = useState('form03')

  // Form 03 State
  const initialF03 = {
    formType: 'SUSL/GA/TD/03',
    name: '', desig: '', dept: '', status: 'perm',
    nic: '', mob: '', owner: '', vtype: '', reg: '',
    eng: '', chs: '', agree: false, sig1: '', d1: new Date().toISOString().split('T')[0]
  }
  const [f03, setF03] = useState(initialF03)

  // Form 01 State
  const initialF01 = {
    formType: 'SUSL/GA/TD/01',
    name: '', tel: '', desig: '', dept: '', reason: '',
    pax: '1', vt: '',
    passengers: [
      { name: '', desig: '' },
      { name: '', desig: '' },
      { name: '', desig: '' },
      { name: '', desig: '' }
    ],
    place: '', route: '',
    dd: new Date().toISOString().split('T')[0], dt: '',
    od: new Date().toISOString().split('T')[0], ot: '',
    rd: new Date().toISOString().split('T')[0], rt: '',
    agree: false, sig: '', sd: new Date().toISOString().split('T')[0]
  }
  const [f01, setF01] = useState(initialF01)

  // Common UI states
  const [submitting, setSubmitting] = useState(false)
  const [resultPass, setResultPass] = useState(null)
  const [feedbackMsg, setFeedbackMsg] = useState({ text: '', type: '' })
  const [copied, setCopied] = useState(false)

  // Status Search State
  const [searchQuery, setSearchQuery] = useState('')
  const [searching, setSearching] = useState(false)
  const [searchResult, setSearchResult] = useState(null)
  const [searchError, setSearchError] = useState('')

  const curT3 = T3[lang]
  const curT1 = T1[lang]

  // Submit Handler Form 03
  const handleF03Submit = async (e) => {
    e.preventDefault()
    setFeedbackMsg({ text: '', type: '' })

    if (!f03.name || !f03.desig || !f03.dept || !f03.nic || !f03.mob || !f03.owner || !f03.vtype || !f03.reg) {
      setFeedbackMsg({ text: curT3.bad, type: 'err' })
      return
    }
    if (!f03.agree) {
      setFeedbackMsg({ text: lang === 'si' ? 'කරුණාකර ප්‍රකාශය කියවා එකඟතාව සලකුණු කරන්න.' : 'Please agree to the declaration.', type: 'err' })
      return
    }

    setSubmitting(true)
    try {
      const res = await vehiclePassAPI.apply(f03)
      if (res.data?.success && res.data?.data) {
        setResultPass(res.data.data)
        setFeedbackMsg({ text: curT3.ok, type: 'ok' })
      } else {
        setFeedbackMsg({ text: res.data?.message || 'Submission failed', type: 'err' })
      }
    } catch (err) {
      setFeedbackMsg({ text: err.response?.data?.message || 'Submission error', type: 'err' })
    } finally {
      setSubmitting(false)
    }
  }

  // Submit Handler Form 01
  const handleF01Submit = async (e) => {
    e.preventDefault()
    setFeedbackMsg({ text: '', type: '' })

    if (!f01.name || !f01.tel || !f01.desig || !f01.dept || !f01.reason || !f01.place || !f01.route) {
      setFeedbackMsg({ text: curT1.bad, type: 'err' })
      return
    }
    if (!f01.agree) {
      setFeedbackMsg({ text: lang === 'si' ? 'කරුණාකර ප්‍රකාශය කියවා එකඟතාව සලකුණු කරන්න.' : 'Please agree to the declaration.', type: 'err' })
      return
    }

    setSubmitting(true)
    try {
      const res = await vehiclePassAPI.apply(f01)
      if (res.data?.success && res.data?.data) {
        setResultPass(res.data.data)
        setFeedbackMsg({ text: curT1.ok, type: 'ok' })
      } else {
        setFeedbackMsg({ text: res.data?.message || 'Submission failed', type: 'err' })
      }
    } catch (err) {
      setFeedbackMsg({ text: err.response?.data?.message || 'Submission error', type: 'err' })
    } finally {
      setSubmitting(false)
    }
  }

  // Check Status Handler
  const handleCheckStatus = async (e) => {
    e.preventDefault()
    setSearchError('')
    setSearchResult(null)
    if (!searchQuery.trim()) return

    setSearching(true)
    try {
      const res = await vehiclePassAPI.checkStatus(searchQuery.trim())
      if (res.data?.success && res.data?.data) {
        setSearchResult(res.data.data)
      } else {
        setSearchError('No pass found')
      }
    } catch (err) {
      setSearchError(err.response?.data?.message || 'No application found matching this reference code or phone number.')
    } finally {
      setSearching(false)
    }
  }

  const handleCopyCode = (code) => {
    navigator.clipboard.writeText(code)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className={`vp-page ${isLight ? 'theme-light' : ''}`}>
      {/* ── Top Bar ── */}
      <nav className="susl-topbar">
        <div className="susl-brand-group" onClick={() => navigate('/login')}>
          <img src={logo} alt="V-MAS Logo" className="susl-brand-logo" />
          <div className="susl-brand-titles">
            <span className="susl-brand-title">V-MAS • SUSL VEHICLE PERMITS</span>
            <span className="susl-brand-sub">Sabaragamuwa University of Sri Lanka</span>
          </div>
        </div>

        <div className="susl-top-controls">
          {/* Language Toggle */}
          <div className="susl-lang-toggle" role="group" aria-label="Language">
            <button
              type="button"
              className={`susl-lang-btn ${lang === 'si' ? 'active' : ''}`}
              onClick={() => setLang('si')}
            >
              සිංහල
            </button>
            <button
              type="button"
              className={`susl-lang-btn ${lang === 'en' ? 'active' : ''}`}
              onClick={() => setLang('en')}
            >
              English
            </button>
          </div>

          <button
            type="button"
            className="susl-lang-btn"
            style={{ border: '1.5px solid var(--susl-line)', borderRadius: 8 }}
            onClick={() => setTheme(isLight ? 'blue' : 'light')}
            title="Toggle theme"
          >
            {isLight ? <Moon size={15} /> : <Sun size={15} />}
          </button>

          <button
            type="button"
            className="susl-lang-btn"
            style={{ border: '1.5px solid var(--susl-line)', borderRadius: 8, display: 'inline-flex', alignItems: 'center', gap: 6 }}
            onClick={() => navigate('/login')}
          >
            <ArrowLeft size={14} />
            <span>{lang === 'si' ? 'ආපසු' : 'Back'}</span>
          </button>
        </div>
      </nav>

      {/* ── Two Form Options & Status Checker Tabs ── */}
      <div className="susl-form-selector-wrap">
        <div className="susl-form-tabs">
          <button
            type="button"
            className={`susl-form-tab ${activeTab === 'form03' ? 'active' : ''}`}
            onClick={() => { setActiveTab('form03'); setResultPass(null); setFeedbackMsg({ text: '', type: '' }); }}
          >
            <Ticket size={16} />
            <span>
              <strong>SUSL/GA/TD/03</strong> • {lang === 'si' ? 'කාර්යය මණ්ඩල වාහන හඳුනාගැනීම' : 'Staff Vehicle ID Card'}
            </span>
          </button>

          <button
            type="button"
            className={`susl-form-tab ${activeTab === 'form01' ? 'active' : ''}`}
            onClick={() => { setActiveTab('form01'); setResultPass(null); setFeedbackMsg({ text: '', type: '' }); }}
          >
            <Truck size={16} />
            <span>
              <strong>SUSL/GA/TD/01</strong> • {lang === 'si' ? 'පිටස්තර ගමන් අවසර පත්‍රය' : 'Outstation Gate Pass'}
            </span>
          </button>

          <button
            type="button"
            className={`susl-form-tab ${activeTab === 'check' ? 'active' : ''}`}
            onClick={() => { setActiveTab('check'); setResultPass(null); }}
          >
            <Search size={16} />
            <span>{lang === 'si' ? 'තත්ත්වය පරීක්ෂා කිරීම' : 'Check Status'}</span>
          </button>
        </div>
      </div>

      {/* ── SUCCESS NOTICE BANNER (if submitted) ── */}
      {resultPass && (
        <div style={{ maxWidth: 860, margin: '0 auto 16px', padding: '0 16px' }}>
          <div className="susl-success-banner">
            <CheckCircle size={36} color="#10b981" style={{ margin: '0 auto 8px' }} />
            <h3 style={{ margin: '0 0 4px', fontSize: '1.25rem', color: '#10b981' }}>
              {lang === 'si' ? 'අයදුම්පත සාර්ථකව පද්ධතියට එක් විය!' : 'Application Submitted Successfully!'}
            </h3>
            <p style={{ margin: '0 0 10px', fontSize: '0.9rem', color: 'var(--susl-muted)' }}>
              {lang === 'si' ? 'ඔබගේ අයදුම්පත් ලුහුබැඳීමේ අංකය (Pass Reference Code):' : 'Your Pass Tracking Reference Code:'}
            </p>
            <div>
              <span className="susl-code-badge">{resultPass.passCode}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'center', gap: 10, marginTop: 12 }}>
              <button
                type="button"
                className="btn"
                style={{ padding: '8px 16px', fontSize: '0.85rem' }}
                onClick={() => handleCopyCode(resultPass.passCode)}
              >
                {copied ? <Check size={14} /> : <Copy size={14} />}
                <span>{copied ? 'Copied' : 'Copy Code'}</span>
              </button>
              <button
                type="button"
                className="btn alt"
                style={{ padding: '8px 16px', fontSize: '0.85rem' }}
                onClick={() => window.print()}
              >
                <Printer size={14} />
                <span>{lang === 'si' ? 'මුද්‍රණය කරන්න' : 'Print Form'}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ══════════════════════════════════════════════════════════════════════
          OPTION 1: FORM SUSL/GA/TD/03 — STAFF VEHICLE IDENTIFICATION
          ══════════════════════════════════════════════════════════════════════ */}
      {activeTab === 'form03' && (
        <form className="paper" onSubmit={handleF03Submit} noValidate>
          <span className="ref">{curT3.ref}</span>
          <header>
            <p className="uni">{curT3.uni}</p>
            <p className="uni-en">{curT3.uniEn}</p>
            <h1>{curT3.title}</h1>
          </header>

          <h2>1. <span>{curT3.s1}</span></h2>
          <div className="grid">
            <div className="full">
              <label htmlFor="f03-name"><span className="n">1.1</span>{curT3.name}</label>
              <input
                id="f03-name"
                required
                value={f03.name}
                onChange={e => setF03({ ...f03, name: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-desig"><span className="n">1.2</span>{curT3.desig}</label>
              <input
                id="f03-desig"
                required
                value={f03.desig}
                onChange={e => setF03({ ...f03, desig: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-dept"><span className="n">1.3</span>{curT3.dept}</label>
              <input
                id="f03-dept"
                required
                value={f03.dept}
                onChange={e => setF03({ ...f03, dept: e.target.value })}
              />
            </div>
            <div className="full">
              <label id="stl-03"><span className="n">1.4</span>{curT3.status}</label>
              <div className="radios" role="radiogroup" aria-labelledby="stl-03">
                <label>
                  <input
                    type="radio"
                    name="f03-status"
                    value="perm"
                    checked={f03.status === 'perm'}
                    onChange={() => setF03({ ...f03, status: 'perm' })}
                  />
                  <span>{curT3.perm}</span>
                </label>
                <label>
                  <input
                    type="radio"
                    name="f03-status"
                    value="temp"
                    checked={f03.status === 'temp'}
                    onChange={() => setF03({ ...f03, status: 'temp' })}
                  />
                  <span>{curT3.temp}</span>
                </label>
              </div>
            </div>
            <div>
              <label htmlFor="f03-nic"><span className="n">1.5</span>{curT3.nic}</label>
              <input
                id="f03-nic"
                required
                value={f03.nic}
                onChange={e => setF03({ ...f03, nic: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-mob"><span className="n">1.6</span>{curT3.mob}</label>
              <input
                id="f03-mob"
                type="tel"
                required
                value={f03.mob}
                onChange={e => setF03({ ...f03, mob: e.target.value })}
              />
            </div>
          </div>

          <h2>2. <span>{curT3.s2}</span></h2>
          <div className="grid">
            <div className="full">
              <label htmlFor="f03-owner"><span className="n">2.1</span>{curT3.owner}</label>
              <input
                id="f03-owner"
                required
                value={f03.owner}
                onChange={e => setF03({ ...f03, owner: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-vtype"><span className="n">2.2</span>{curT3.vtype}</label>
              <input
                id="f03-vtype"
                required
                placeholder="e.g. Car / Motorbike / Van"
                value={f03.vtype}
                onChange={e => setF03({ ...f03, vtype: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-reg"><span className="n">2.3</span>{curT3.reg}</label>
              <input
                id="f03-reg"
                required
                placeholder="e.g. WP CAB-1234"
                value={f03.reg}
                onChange={e => setF03({ ...f03, reg: e.target.value.toUpperCase() })}
                style={{ textTransform: 'uppercase', fontWeight: 600 }}
              />
            </div>
            <div>
              <label htmlFor="f03-eng"><span className="n">2.4</span>{curT3.eng}</label>
              <input
                id="f03-eng"
                value={f03.eng}
                onChange={e => setF03({ ...f03, eng: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f03-chs"><span className="n">2.5</span>{curT3.chs}</label>
              <input
                id="f03-chs"
                value={f03.chs}
                onChange={e => setF03({ ...f03, chs: e.target.value })}
              />
            </div>
          </div>

          <p className="note">* {curT3.n1}</p>
          <p className="note">* {curT3.n2}</p>

          <p className="decl">{curT3.decl}</p>
          <div className="check">
            <input
              type="checkbox"
              id="f03-agree"
              checked={f03.agree}
              onChange={e => setF03({ ...f03, agree: e.target.checked })}
              required
            />
            <label htmlFor="f03-agree" style={{ fontWeight: 400, margin: 0, cursor: 'pointer' }}>
              {curT3.agree}
            </label>
          </div>

          <div className="sig">
            <div>
              <label htmlFor="f03-sig1">{curT3.asig}</label>
              <input
                id="f03-sig1"
                placeholder="Type full name as digital signature"
                value={f03.sig1}
                onChange={e => setF03({ ...f03, sig1: e.target.value })}
                required
              />
            </div>
            <div>
              <label htmlFor="f03-d1">{curT3.date}</label>
              <input
                id="f03-d1"
                type="date"
                value={f03.d1}
                onChange={e => setF03({ ...f03, d1: e.target.value })}
                required
              />
            </div>
          </div>

          {/* Official Section */}
          <section className="official">
            <p className="tag">{curT3.off}</p>
            <h3>{curT3.h3a}</h3>
            <p style={{ fontSize: '0.88rem', color: 'var(--susl-muted)', margin: '4px 0 10px' }}>{curT3.t3a}</p>
            <div className="sig">
              <div><label>{curT3.hsig}</label><input disabled placeholder="Pending recommendation" /></div>
              <div><label>{curT3.date}</label><input disabled type="date" /></div>
            </div>

            <h3>{curT3.h3b}</h3>
            <p style={{ fontSize: '0.88rem', color: 'var(--susl-muted)', margin: '4px 0 10px' }}>{curT3.t3b}</p>
            <div className="sig">
              <div><label>{curT3.vsig}</label><input disabled placeholder="Pending approval" /></div>
              <div><label>{curT3.date}</label><input disabled type="date" /></div>
            </div>

            <h3>{curT3.h3c}</h3>
            <div className="sig">
              <div><label>{curT3.rcv}</label><input disabled type="date" /></div>
              <div></div>
            </div>
          </section>

          <div className="actions">
            <button className="btn" type="submit" disabled={submitting}>
              {submitting ? <RefreshCw size={16} className="animate-spin" /> : <Send size={16} />}
              <span>{curT3.submit}</span>
            </button>
            <button className="btn alt" type="button" onClick={() => window.print()}>
              <Printer size={16} />
              <span>{curT3.print}</span>
            </button>
            <button className="btn alt" type="button" onClick={() => setF03(initialF03)}>
              <RotateCcw size={16} />
              <span>{curT3.reset}</span>
            </button>
          </div>

          {feedbackMsg.text && (
            <p id="msg" style={{ color: feedbackMsg.type === 'err' ? 'var(--susl-err)' : '#10b981' }}>
              {feedbackMsg.text}
            </p>
          )}
        </form>
      )}

      {/* ══════════════════════════════════════════════════════════════════════
          OPTION 2: FORM SUSL/GA/TD/01 — OUTSTATION TRAVEL GATE PASS
          ══════════════════════════════════════════════════════════════════════ */}
      {activeTab === 'form01' && (
        <form className="paper" onSubmit={handleF01Submit} noValidate>
          <span className="ref">{curT1.ref}</span>
          <header>
            <p className="uni">{curT1.uni}</p>
            <p className="uni-en">{curT1.uniEn}</p>
            <h1>{curT1.title}</h1>
          </header>

          <h2>{curT1.s1}</h2>
          <div className="grid">
            <div>
              <label htmlFor="f01-name"><span className="n">1</span>{curT1.name}</label>
              <input
                id="f01-name"
                required
                value={f01.name}
                onChange={e => setF01({ ...f01, name: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-tel">{curT1.tel}</label>
              <input
                id="f01-tel"
                type="tel"
                required
                value={f01.tel}
                onChange={e => setF01({ ...f01, tel: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-desig"><span className="n">2</span>{curT1.desig}</label>
              <input
                id="f01-desig"
                required
                value={f01.desig}
                onChange={e => setF01({ ...f01, desig: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-dept"><span className="n">3</span>{curT1.dept}</label>
              <input
                id="f01-dept"
                required
                value={f01.dept}
                onChange={e => setF01({ ...f01, dept: e.target.value })}
              />
            </div>

            <div className="full">
              <label htmlFor="f01-reason">
                <span className="n">4</span>{curT1.reason} <span className="sub">{curT1.reasonSub}</span>
              </label>
              <textarea
                id="f01-reason"
                required
                value={f01.reason}
                onChange={e => setF01({ ...f01, reason: e.target.value })}
              />
            </div>

            <div>
              <label htmlFor="f01-pax"><span className="n">5</span>{curT1.pax}</label>
              <input
                id="f01-pax"
                type="number"
                min="1"
                required
                value={f01.pax}
                onChange={e => setF01({ ...f01, pax: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-vt">{curT1.vt}</label>
              <input
                id="f01-vt"
                required
                placeholder="e.g. Van / Car / Bus"
                value={f01.vt}
                onChange={e => setF01({ ...f01, vt: e.target.value })}
              />
            </div>

            {/* Passengers Table */}
            <div className="full">
              <label><span className="n">6</span>{curT1.ppl}</label>
              <div className="tw">
                <table>
                  <thead>
                    <tr>
                      <th>{curT1.sn}</th>
                      <th>{curT1.pn}</th>
                      <th>{curT1.desig}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {f01.passengers.map((p, idx) => (
                      <tr key={idx}>
                        <td>{idx + 1}</td>
                        <td>
                          <input
                            aria-label={`Name ${idx + 1}`}
                            placeholder="Passenger Name"
                            value={p.name}
                            onChange={e => {
                              const updated = [...f01.passengers]
                              updated[idx].name = e.target.value
                              setF01({ ...f01, passengers: updated })
                            }}
                          />
                        </td>
                        <td>
                          <input
                            aria-label={`Designation ${idx + 1}`}
                            placeholder="Designation"
                            value={p.desig}
                            onChange={e => {
                              const updated = [...f01.passengers]
                              updated[idx].desig = e.target.value
                              setF01({ ...f01, passengers: updated })
                            }}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="full">
              <label htmlFor="f01-place"><span className="n">7</span>{curT1.place}</label>
              <input
                id="f01-place"
                required
                placeholder="e.g. Colombo, Ministry of Education, Kandy Campus"
                value={f01.place}
                onChange={e => setF01({ ...f01, place: e.target.value })}
              />
            </div>
            <div className="full">
              <label htmlFor="f01-route"><span className="n">8</span>{curT1.route}</label>
              <input
                id="f01-route"
                required
                placeholder="e.g. Belihuloya -> Ratnapura -> Colombo High Level Road"
                value={f01.route}
                onChange={e => setF01({ ...f01, route: e.target.value })}
              />
            </div>

            <div>
              <label htmlFor="f01-dd"><span className="n">9</span>{curT1.due}</label>
              <input
                id="f01-dd"
                type="date"
                required
                value={f01.dd}
                onChange={e => setF01({ ...f01, dd: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-dt">{curT1.time}</label>
              <input
                id="f01-dt"
                type="time"
                value={f01.dt}
                onChange={e => setF01({ ...f01, dt: e.target.value })}
              />
            </div>

            <div>
              <label htmlFor="f01-od"><span className="n">10</span>{curT1.dep}</label>
              <input
                id="f01-od"
                type="date"
                required
                value={f01.od}
                onChange={e => setF01({ ...f01, od: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-ot">{curT1.tdep}</label>
              <input
                id="f01-ot"
                type="time"
                value={f01.ot}
                onChange={e => setF01({ ...f01, ot: e.target.value })}
              />
            </div>

            <div>
              <label htmlFor="f01-rd"><span className="n">11</span>{curT1.ret}</label>
              <input
                id="f01-rd"
                type="date"
                required
                value={f01.rd}
                onChange={e => setF01({ ...f01, rd: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-rt">{curT1.tret}</label>
              <input
                id="f01-rt"
                type="time"
                value={f01.rt}
                onChange={e => setF01({ ...f01, rt: e.target.value })}
              />
            </div>
          </div>

          <p className="decl">{curT1.decl}</p>
          <div className="check">
            <input
              type="checkbox"
              id="f01-agree"
              checked={f01.agree}
              onChange={e => setF01({ ...f01, agree: e.target.checked })}
              required
            />
            <label htmlFor="f01-agree" style={{ fontWeight: 400, margin: 0, cursor: 'pointer' }}>
              {curT1.agree}
            </label>
          </div>

          <div className="grid">
            <div>
              <label htmlFor="f01-sig">{curT1.asig}</label>
              <input
                id="f01-sig"
                placeholder="Type applicant full name as digital signature"
                required
                value={f01.sig}
                onChange={e => setF01({ ...f01, sig: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="f01-sd">{curT1.date}</label>
              <input
                id="f01-sd"
                type="date"
                required
                value={f01.sd}
                onChange={e => setF01({ ...f01, sd: e.target.value })}
              />
            </div>
          </div>

          {/* Section 2: Recommendations */}
          <h2>{curT1.s2}</h2>
          <div className="box">
            <h3>{curT1.hod}</h3>
            <div className="grid">
              <div><label>{curT1.rec}</label><select disabled><option>{curT1.yes}</option></select></div>
              <div><label>{curT1.date}</label><input disabled type="date" /></div>
              <div className="full"><label>{curT1.sigl}</label><input disabled placeholder="Pending recommendation" /></div>
            </div>

            <h3>{curT1.dean}</h3>
            <div className="grid">
              <div><label>{curT1.rec}</label><select disabled><option>{curT1.yes}</option></select></div>
              <div><label>{curT1.date}</label><input disabled type="date" /></div>
              <div className="full"><label>{curT1.sigl}</label><input disabled placeholder="Pending recommendation" /></div>
            </div>
          </div>

          {/* Section 3: Instructions */}
          <h2>{curT1.s3}</h2>
          <ol className="rules">
            <li>{curT1.i1}</li>
            <li>{curT1.i2}</li>
            <li>{curT1.i3}</li>
            <li>{curT1.i4}</li>
            <li>{curT1.i5}</li>
            <li>{curT1.i6}</li>
          </ol>

          {/* Section 4: For Office Use Only */}
          <h2>{curT1.s4}</h2>
          <div className="box">
            <p className="tag">{curT1.off}</p>
            <h3>{curT1.ga1}</h3>
            <div className="grid">
              <div className="full"><label>{curT1.obs}</label><textarea disabled placeholder="Observations will be recorded by General Administration upon review"></textarea></div>
              <div><label>{curT1.date}</label><input disabled type="date" /></div>
            </div>

            <h3>{curT1.ga2}</h3>
            <div className="grid">
              <div><label>{curT1.appr}</label><select disabled><option>{curT1.ayes}</option></select></div>
              <div><label>{curT1.date}</label><input disabled type="date" /></div>
              <div className="full"><label>{curT1.vc}</label><input disabled placeholder="Pending authorization" /></div>
            </div>

            <h3>{curT1.iss}</h3>
            <div className="grid">
              <div><label>{curT1.vno}</label><input disabled placeholder="To be assigned by controller" /></div>
              <div><label>{curT1.drv}</label><input disabled placeholder="To be assigned by controller" /></div>
              <div><label>{curT1.isig}</label><input disabled /></div>
              <div><label>{curT1.date}</label><input disabled type="date" /></div>
            </div>
          </div>

          {/* Section 5: For Security Division */}
          <h2>{curT1.s5}</h2>
          <div className="box">
            <p className="tag">{curT1.off}</p>
            <div className="grid">
              <div><label>{curT1.gout}</label><input disabled type="date" /></div>
              <div><label>{curT1.time}</label><input disabled type="time" /></div>
              <div><label>{curT1.mil}</label><input disabled placeholder="Outward mileage" /></div>
              <div></div>
              <div><label>{curT1.gin}</label><input disabled type="date" /></div>
              <div><label>{curT1.time}</label><input disabled type="time" /></div>
              <div><label>{curT1.mil}</label><input disabled placeholder="Inward mileage" /></div>
              <div></div>
              <div><label>{curT1.gname}</label><input disabled placeholder="Security Officer" /></div>
              <div><label>{curT1.sigl}</label><input disabled /></div>
            </div>
          </div>

          <div className="actions">
            <button className="btn" type="submit" disabled={submitting}>
              {submitting ? <RefreshCw size={16} className="animate-spin" /> : <Send size={16} />}
              <span>{curT1.submit}</span>
            </button>
            <button className="btn alt" type="button" onClick={() => window.print()}>
              <Printer size={16} />
              <span>{curT1.print}</span>
            </button>
            <button className="btn alt" type="button" onClick={() => setF01(initialF01)}>
              <RotateCcw size={16} />
              <span>{curT1.reset}</span>
            </button>
          </div>

          {feedbackMsg.text && (
            <p id="msg" style={{ color: feedbackMsg.type === 'err' ? 'var(--susl-err)' : '#10b981' }}>
              {feedbackMsg.text}
            </p>
          )}
        </form>
      )}

      {/* ══════════════════════════════════════════════════════════════════════
          OPTION 3: CHECK PASS STATUS
          ══════════════════════════════════════════════════════════════════════ */}
      {activeTab === 'check' && (
        <div className="paper">
          <header style={{ marginBottom: 20 }}>
            <h1 style={{ textDecoration: 'none', fontSize: '1.4rem' }}>
              {lang === 'si' ? 'අයදුම්පතේ තත්ත්වය පරීක්ෂා කිරීම' : 'Check Vehicle Pass Status'}
            </h1>
            <p style={{ color: 'var(--susl-muted)', fontSize: '0.9rem', margin: '4px 0 0' }}>
              {lang === 'si'
                ? 'ඔබගේ අයදුම්පත් අංකය (උදා: VP-STF-2026-XXXX හෝ VP-OUT-2026-XXXX) හෝ ජංගම දුරකථන අංකය ඇතුළත් කරන්න.'
                : 'Enter your Pass Tracking Reference Code (e.g. VP-STF-2026-XXXX or VP-OUT-2026-XXXX) or Phone Number.'}
            </p>
          </header>

          <form onSubmit={handleCheckStatus} style={{ display: 'flex', gap: 10, marginBottom: 24 }}>
            <input
              type="text"
              placeholder={lang === 'si' ? 'අයදුම්පත් අංකය හෝ දුරකථන අංකය ඇතුළත් කරන්න...' : 'Enter pass code or phone number...'}
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              required
              style={{ flex: 1 }}
            />
            <button type="submit" className="btn" disabled={searching}>
              {searching ? <RefreshCw size={16} className="animate-spin" /> : <Search size={16} />}
              <span>{lang === 'si' ? 'සොයන්න' : 'Search'}</span>
            </button>
          </form>

          {searchError && (
            <div style={{
              display: 'flex', alignItems: 'center', gap: 10,
              padding: '12px 16px', borderRadius: 8,
              background: 'rgba(239, 68, 68, 0.12)', border: '1px solid rgba(239, 68, 68, 0.3)',
              color: 'var(--susl-err)', fontSize: '0.9rem', marginBottom: 20
            }}>
              <AlertCircle size={18} />
              <span>{searchError}</span>
            </div>
          )}

          {searchResult && (
            <div style={{
              background: 'var(--susl-field)', border: '1px solid var(--susl-line)',
              borderRadius: 10, padding: 24, textAlign: 'left'
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12, marginBottom: 16 }}>
                <div>
                  <span style={{ fontSize: '0.75rem', color: 'var(--susl-muted)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                    {searchResult.formType || 'PASS CODE'}
                  </span>
                  <div style={{ fontSize: '1.4rem', fontWeight: 800, fontFamily: 'monospace', color: 'var(--susl-accent)' }}>
                    {searchResult.passCode}
                  </div>
                </div>

                <span style={{
                  padding: '4px 14px', borderRadius: 999, fontWeight: 800, fontSize: '0.8rem',
                  background: searchResult.status === 'APPROVED' ? 'rgba(16, 185, 129, 0.15)' : searchResult.status === 'REJECTED' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                  color: searchResult.status === 'APPROVED' ? '#10b981' : searchResult.status === 'REJECTED' ? '#ef4444' : '#f59e0b',
                  border: `1px solid ${searchResult.status === 'APPROVED' ? 'rgba(16, 185, 129, 0.3)' : searchResult.status === 'REJECTED' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(245, 158, 11, 0.3)'}`
                }}>
                  {searchResult.status}
                </span>
              </div>

              {searchResult.status === 'REJECTED' && searchResult.rejectionReason && (
                <div style={{
                  padding: '10px 14px', borderRadius: 8,
                  background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.25)',
                  color: '#fca5a5', fontSize: '0.88rem', marginBottom: 14
                }}>
                  <strong>{lang === 'si' ? 'ප්‍රතික්ෂේප කිරීමට හේතුව:' : 'Reason for rejection:'}</strong> {searchResult.rejectionReason}
                </div>
              )}

              {searchResult.status === 'APPROVED' && (
                <div style={{
                  padding: '10px 14px', borderRadius: 8,
                  background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.25)',
                  color: '#6ee7b7', fontSize: '0.88rem', marginBottom: 14
                }}>
                  <strong>{lang === 'si' ? 'අනුමැතිය හිමිවිය:' : 'Approved by:'}</strong> {searchResult.approvedBy || 'Administration'}
                </div>
              )}

              <div className="grid">
                <div><label className="sub">{lang === 'si' ? 'අයදුම්කරු' : 'Applicant'}</label><div style={{ fontWeight: 600 }}>{searchResult.applicantName}</div></div>
                <div><label className="sub">{lang === 'si' ? 'දුරකථනය' : 'Phone'}</label><div style={{ fontWeight: 600 }}>{searchResult.phoneNumber}</div></div>
                <div><label className="sub">{lang === 'si' ? 'වාහන අංකය' : 'Vehicle Reg'}</label><div style={{ fontWeight: 700 }}>{searchResult.vehicleRegNumber}</div></div>
                <div><label className="sub">{lang === 'si' ? 'වාහන වර්ගය' : 'Vehicle Type'}</label><div style={{ fontWeight: 600 }}>{searchResult.vehicleType}</div></div>
                <div className="full"><label className="sub">{lang === 'si' ? 'අරමුණ / ගමනාන්තය' : 'Purpose / Destination'}</label><div>{searchResult.purpose}</div></div>
              </div>

              <div style={{ display: 'flex', gap: 10, marginTop: 20 }}>
                <button type="button" className="btn" onClick={() => window.print()}>
                  <Printer size={15} />
                  <span>{lang === 'si' ? 'මුද්‍රණය කරන්න' : 'Print Slip'}</span>
                </button>
                <button type="button" className="btn alt" onClick={() => handleCopyCode(searchResult.passCode)}>
                  <Copy size={15} />
                  <span>{copied ? 'Copied' : 'Copy Reference'}</span>
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export default VehiclePassPage
