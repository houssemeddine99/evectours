    (function () {
      /* Ensure body/html never trap fixed children via an active transform */
      document.body.style.transform      = 'none';
      document.documentElement.style.transform = 'none';

      /* Move mascot elements to <html> so they escape any body transform */
      var _bot    = document.getElementById('globe-bot');
      var _canvas = document.getElementById('gb-canvas');
      var _chat   = document.getElementById('gb-chat');
      if (_bot)    document.documentElement.appendChild(_bot);
      if (_canvas) document.documentElement.appendChild(_canvas);
      if (_chat)   document.documentElement.appendChild(_chat);

      /* ── SVG definitions for all 10 characters ── */
      const CHARS = [
        {
          name: 'Orbit', trait: 'Friendly • Energetic',
          color: 'rgba(79,110,247,0.45)',
          msgs: ['✈️ Let\'s go somewhere!', '🌍 I\'m Orbit — your guide!', '⚡ Full energy today!'],
          eyeL: [46,60], eyeR: [78,60],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- body -->
            <circle cx="60" cy="68" r="50" fill="#4f6ef7"/>
            <ellipse cx="60" cy="68" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.35"/>
            <line x1="10" y1="68" x2="110" y2="68" stroke="white" stroke-width="1.8" opacity="0.35"/>
            <line x1="18" y1="42" x2="102" y2="42" stroke="white" stroke-width="1.3" opacity="0.25"/>
            <line x1="18" y1="94" x2="102" y2="94" stroke="white" stroke-width="1.3" opacity="0.25"/>
            <ellipse cx="40" cy="46" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 46)"/>
            <!-- orange cap -->
            <ellipse cx="60" cy="20" rx="36" ry="10" fill="#ff6b35"/>
            <rect x="24" y="16" width="72" height="12" rx="6" fill="#ff6b35"/>
            <rect x="14" y="24" width="92" height="7" rx="3.5" fill="#e05a28"/>
            <!-- cap button -->
            <circle cx="60" cy="16" r="4" fill="#e05a28"/>
            <!-- eyes -->
            <circle cx="44" cy="64" r="10" fill="white"/>
            <circle cx="76" cy="64" r="10" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="78" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle cx="48" cy="62" r="2" fill="white"/>
            <circle cx="80" cy="62" r="2" fill="white"/>
            <!-- smile -->
            <path d="M44 82 Q60 96 76 82" stroke="white" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            <!-- cheeks -->
            <ellipse cx="36" cy="78" rx="7" ry="4" fill="#ff9999" opacity="0.5"/>
            <ellipse cx="84" cy="78" rx="7" ry="4" fill="#ff9999" opacity="0.5"/>
            <!-- left arm waving -->
            <path class="arm-wave" d="M10 76 Q-2 62 6 50" stroke="#3a5ce0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="6" cy="48" r="8" fill="#FFD6A5"/>
            <!-- right arm -->
            <path d="M110 76 Q118 88 110 100" stroke="#3a5ce0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs -->
            <rect x="43" y="116" width="15" height="24" rx="7.5" fill="#3a5ce0"/>
            <rect x="62" y="116" width="15" height="24" rx="7.5" fill="#3a5ce0"/>
            <!-- sneakers -->
            <rect x="34" y="134" width="26" height="10" rx="5" fill="white"/>
            <rect x="34" y="134" width="10" height="10" rx="5" fill="#ff6b35"/>
            <rect x="53" y="134" width="26" height="10" rx="5" fill="white"/>
            <rect x="53" y="134" width="10" height="10" rx="5" fill="#ff6b35"/>
          </svg>`
        },
        {
          name: 'Jetto', trait: 'Adventurous • Curious',
          color: 'rgba(32,178,170,0.45)',
          msgs: ['📸 Capturing the world!', '🌊 Adventure awaits!', '🎒 Jetto at your service!'],
          eyeL: [44,64], eyeR: [76,64],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- teal body -->
            <circle cx="60" cy="68" r="50" fill="#20b2aa"/>
            <ellipse cx="60" cy="68" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <line x1="10" y1="68" x2="110" y2="68" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <line x1="18" y1="44" x2="102" y2="44" stroke="white" stroke-width="1.3" opacity="0.22"/>
            <line x1="18" y1="92" x2="102" y2="92" stroke="white" stroke-width="1.3" opacity="0.22"/>
            <ellipse cx="40" cy="46" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 46)"/>
            <!-- straw hat -->
            <ellipse cx="60" cy="22" rx="44" ry="10" fill="#d4a853"/>
            <ellipse cx="60" cy="18" rx="28" ry="10" fill="#c8962c"/>
            <rect x="32" y="12" width="56" height="14" rx="7" fill="#c8962c"/>
            <rect x="34" y="22" width="52" height="4" rx="2" fill="#8B6914" opacity="0.5"/>
            <!-- camera hanging -->
            <rect x="68" y="86" width="22" height="16" rx="4" fill="#2d3436"/>
            <circle cx="79" cy="94" r="5" fill="#636e72"/>
            <circle cx="79" cy="94" r="3" fill="#b2bec3"/>
            <rect x="72" y="83" width="14" height="5" rx="2" fill="#2d3436"/>
            <line x1="68" y1="86" x2="52" y2="76" stroke="#2d3436" stroke-width="2.5" stroke-linecap="round"/>
            <!-- backpack straps on chest -->
            <path d="M44 100 Q36 90 40 78" stroke="#8B6914" stroke-width="4" fill="none" stroke-linecap="round"/>
            <path d="M76 100 Q84 90 80 78" stroke="#8B6914" stroke-width="4" fill="none" stroke-linecap="round"/>
            <!-- eyes -->
            <circle cx="44" cy="62" r="10" fill="white"/>
            <circle cx="76" cy="62" r="10" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="62" r="5.5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="78" cy="62" r="5.5" fill="#1a1a2e"/>
            <circle cx="48" cy="60" r="2" fill="white"/>
            <circle cx="80" cy="60" r="2" fill="white"/>
            <!-- curious raised brow -->
            <path d="M38 52 Q44 48 50 52" stroke="#0d7c77" stroke-width="2.5" fill="none" stroke-linecap="round"/>
            <path d="M70 50 Q76 46 82 50" stroke="#0d7c77" stroke-width="2.5" fill="none" stroke-linecap="round"/>
            <!-- open curious mouth -->
            <ellipse cx="60" cy="82" rx="10" ry="7" fill="#0d7c77"/>
            <ellipse cx="60" cy="82" rx="7" ry="4" fill="#ff9999" opacity="0.6"/>
            <!-- arm wave -->
            <path class="arm-wave" d="M10 74 Q0 60 8 48" stroke="#199991" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="8" cy="46" r="8" fill="#FFD6A5"/>
            <!-- right arm with camera strap -->
            <path d="M110 74 Q120 84 112 96" stroke="#199991" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs -->
            <rect x="43" y="116" width="15" height="24" rx="7.5" fill="#199991"/>
            <rect x="62" y="116" width="15" height="24" rx="7.5" fill="#199991"/>
            <!-- boots -->
            <rect x="34" y="132" width="24" height="12" rx="5" fill="#5c3d11"/>
            <rect x="56" y="132" width="24" height="12" rx="5" fill="#5c3d11"/>
          </svg>`
        },
        {
          name: 'Nomio', trait: 'Smart • Organized',
          color: 'rgba(79,110,247,0.45)',
          msgs: ['🗺️ I\'ve planned everything!', '📋 Nomio knows the route!', '🤓 Smart travel starts here!'],
          eyeL: [44,62], eyeR: [76,62],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- body -->
            <circle cx="60" cy="68" r="50" fill="#4a6cf7"/>
            <ellipse cx="60" cy="68" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <line x1="10" y1="68" x2="110" y2="68" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <ellipse cx="40" cy="46" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 46)"/>
            <!-- glasses frames -->
            <circle cx="44" cy="62" r="13" fill="none" stroke="#ff8c00" stroke-width="3"/>
            <circle cx="76" cy="62" r="13" fill="none" stroke="#ff8c00" stroke-width="3"/>
            <line x1="57" y1="62" x2="63" y2="62" stroke="#ff8c00" stroke-width="2.5"/>
            <line x1="31" y1="56" x2="24" y2="52" stroke="#ff8c00" stroke-width="2.5"/>
            <line x1="89" y1="56" x2="96" y2="52" stroke="#ff8c00" stroke-width="2.5"/>
            <!-- eyes behind glasses -->
            <circle cx="44" cy="62" r="9" fill="white"/>
            <circle cx="76" cy="62" r="9" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="62" r="5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="78" cy="62" r="5" fill="#1a1a2e"/>
            <circle cx="48" cy="60" r="1.8" fill="white"/>
            <circle cx="80" cy="60" r="1.8" fill="white"/>
            <!-- bow tie -->
            <polygon points="48,88 60,82 72,88 60,94" fill="#ff8c00"/>
            <circle cx="60" cy="88" r="4" fill="#cc6d00"/>
            <!-- hat top -->
            <ellipse cx="60" cy="20" rx="30" ry="9" fill="#1a1a2e"/>
            <rect x="30" y="16" width="60" height="12" rx="4" fill="#1a1a2e"/>
            <rect x="20" y="24" width="80" height="7" rx="3" fill="#1a1a2e"/>
            <!-- map in hand -->
            <rect x="82" y="82" width="24" height="18" rx="3" fill="#f0e6c8"/>
            <line x1="86" y1="88" x2="102" y2="88" stroke="#c8a86e" stroke-width="1.5"/>
            <line x1="86" y1="92" x2="102" y2="92" stroke="#c8a86e" stroke-width="1.5"/>
            <circle cx="94" cy="86" r="2" fill="#ff6b35"/>
            <!-- smile (knowingly) -->
            <path d="M48 78 Q60 86 72 78" stroke="white" stroke-width="3" fill="none" stroke-linecap="round"/>
            <!-- left arm -->
            <path class="arm-wave" d="M10 76 Q2 64 10 52" stroke="#3755d0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="10" cy="50" r="8" fill="#FFD6A5"/>
            <!-- right arm holding map -->
            <path d="M110 76 Q120 80 114 92" stroke="#3755d0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- suitcase -->
            <rect x="90" y="118" width="24" height="18" rx="4" fill="#ff8c00"/>
            <rect x="96" y="114" width="12" height="6" rx="3" fill="none" stroke="#ff8c00" stroke-width="3"/>
            <line x1="90" y1="126" x2="114" y2="126" stroke="#cc6d00" stroke-width="2"/>
            <!-- legs -->
            <rect x="43" y="116" width="15" height="24" rx="7.5" fill="#3755d0"/>
            <rect x="62" y="116" width="15" height="24" rx="7.5" fill="#3755d0"/>
            <rect x="34" y="132" width="24" height="10" rx="5" fill="#1a1a2e"/>
            <rect x="56" y="132" width="24" height="10" rx="5" fill="#1a1a2e"/>
          </svg>`
        },
        {
          name: 'Trailix', trait: 'Explorer • Rugged',
          color: 'rgba(32,160,160,0.45)',
          msgs: ['🥾 Ready to explore!', '⛰️ No trail too tough!', '🧭 Trailix leads the way!'],
          eyeL: [44,66], eyeR: [76,66],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- body teal darker -->
            <circle cx="60" cy="70" r="50" fill="#1a9e9e"/>
            <ellipse cx="60" cy="70" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.28"/>
            <line x1="10" y1="70" x2="110" y2="70" stroke="white" stroke-width="1.8" opacity="0.28"/>
            <ellipse cx="40" cy="48" rx="11" ry="7" fill="white" opacity="0.1" transform="rotate(-25 40 48)"/>
            <!-- hoodie body overlay -->
            <path d="M18 90 Q18 118 60 118 Q102 118 102 90 Q96 78 60 78 Q24 78 18 90Z" fill="#2c3e50" opacity="0.85"/>
            <path d="M36 78 Q42 68 60 68 Q78 68 84 78" fill="#2c3e50" opacity="0.85"/>
            <!-- hoodie pocket -->
            <rect x="46" y="96" width="28" height="14" rx="4" fill="#1a252f" opacity="0.8"/>
            <!-- beanie -->
            <ellipse cx="60" cy="22" rx="34" ry="12" fill="#2c3e50"/>
            <rect x="26" y="18" width="68" height="14" rx="7" fill="#2c3e50"/>
            <rect x="22" y="28" width="76" height="8" rx="4" fill="#1a252f"/>
            <!-- beanie pom pom -->
            <circle cx="60" cy="12" r="8" fill="#4ecdc4"/>
            <!-- eyes -->
            <circle cx="44" cy="64" r="10" fill="white"/>
            <circle cx="76" cy="64" r="10" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="78" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle cx="48" cy="62" r="2" fill="white"/>
            <circle cx="80" cy="62" r="2" fill="white"/>
            <!-- rugged slight frown smirk -->
            <path d="M48 80 Q60 88 72 82" stroke="white" stroke-width="3" fill="none" stroke-linecap="round"/>
            <!-- backpack visible on side -->
            <rect x="88" y="70" width="20" height="30" rx="5" fill="#e67e22"/>
            <rect x="90" y="78" width="16" height="12" rx="3" fill="#d35400"/>
            <line x1="92" y1="70" x2="92" y2="66" stroke="#e67e22" stroke-width="3"/>
            <line x1="104" y1="70" x2="104" y2="66" stroke="#e67e22" stroke-width="3"/>
            <!-- arms -->
            <path class="arm-wave" d="M10 80 Q0 68 8 56" stroke="#14817e" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="7" cy="54" r="8" fill="#FFD6A5"/>
            <path d="M110 80 Q120 88 112 100" stroke="#14817e" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs -->
            <rect x="43" y="116" width="15" height="22" rx="7.5" fill="#2c3e50"/>
            <rect x="62" y="116" width="15" height="22" rx="7.5" fill="#2c3e50"/>
            <!-- boots rugged -->
            <rect x="32" y="130" width="28" height="14" rx="4" fill="#5c3d11"/>
            <rect x="54" y="130" width="28" height="14" rx="4" fill="#5c3d11"/>
            <rect x="32" y="130" width="28" height="5" rx="2" fill="#7a5219"/>
            <rect x="54" y="130" width="28" height="5" rx="2" fill="#7a5219"/>
          </svg>`
        },
        {
          name: 'Guidee', trait: 'Helpful • Supportive',
          color: 'rgba(79,110,247,0.5)',
          msgs: ['🎧 Here to help!', '📞 Got questions? Ask me!', '💡 Guidee at your service!'],
          eyeL: [44,64], eyeR: [76,64],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- body -->
            <circle cx="60" cy="68" r="50" fill="#4f6ef7"/>
            <ellipse cx="60" cy="68" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <line x1="10" y1="68" x2="110" y2="68" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <ellipse cx="40" cy="46" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 46)"/>
            <!-- headset band -->
            <path d="M22 55 Q22 10 60 10 Q98 10 98 55" fill="none" stroke="#1a1a2e" stroke-width="6" stroke-linecap="round"/>
            <!-- ear cups -->
            <rect x="12" y="50" width="16" height="22" rx="8" fill="#1a1a2e"/>
            <rect x="14" y="53" width="12" height="16" rx="6" fill="#ff6b35"/>
            <rect x="92" y="50" width="16" height="22" rx="8" fill="#1a1a2e"/>
            <rect x="94" y="53" width="12" height="16" rx="6" fill="#ff6b35"/>
            <!-- mic boom -->
            <path d="M14 68 Q14 82 24 86" fill="none" stroke="#1a1a2e" stroke-width="3" stroke-linecap="round"/>
            <ellipse cx="27" cy="87" rx="5" ry="3" fill="#ff6b35"/>
            <!-- orange scarf/bandana -->
            <path d="M30 92 Q60 106 90 92 Q80 118 60 118 Q40 118 30 92Z" fill="#ff6b35"/>
            <path d="M30 92 Q60 100 90 92" stroke="#e05a28" stroke-width="2" fill="none"/>
            <!-- eyes -->
            <circle cx="44" cy="64" r="10" fill="white"/>
            <circle cx="76" cy="64" r="10" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="78" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle cx="48" cy="62" r="2" fill="white"/>
            <circle cx="80" cy="62" r="2" fill="white"/>
            <!-- friendly smile -->
            <path d="M44 80 Q60 94 76 80" stroke="white" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            <!-- cheeks -->
            <ellipse cx="36" cy="76" rx="7" ry="4" fill="#ff9999" opacity="0.5"/>
            <ellipse cx="84" cy="76" rx="7" ry="4" fill="#ff9999" opacity="0.5"/>
            <!-- arms -->
            <path class="arm-wave" d="M10 76 Q0 62 8 50" stroke="#3a5ce0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="7" cy="48" r="8" fill="#FFD6A5"/>
            <path d="M110 76 Q120 86 112 98" stroke="#3a5ce0" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs -->
            <rect x="43" y="116" width="15" height="22" rx="7.5" fill="#3a5ce0"/>
            <rect x="62" y="116" width="15" height="22" rx="7.5" fill="#3a5ce0"/>
            <rect x="34" y="130" width="24" height="10" rx="5" fill="#1a1a2e"/>
            <rect x="56" y="130" width="24" height="10" rx="5" fill="#1a1a2e"/>
          </svg>`
        },
        {
          name: 'Voyaj', trait: 'Futuristic • Innovative',
          color: 'rgba(0,200,255,0.45)',
          msgs: ['🚀 Initiating liftoff!', '💫 The future of travel!', '🤖 Voyaj online!'],
          eyeL: [60,68], eyeR: [60,68],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- glow ring -->
            <circle cx="60" cy="70" r="54" fill="none" stroke="rgba(0,200,255,0.2)" stroke-width="6"/>
            <!-- dark futuristic body -->
            <circle cx="60" cy="70" r="48" fill="#0d1b3e"/>
            <circle cx="60" cy="70" r="44" fill="#112244"/>
            <ellipse cx="60" cy="70" rx="22" ry="44" fill="none" stroke="rgba(0,200,255,0.35)" stroke-width="1.5"/>
            <line x1="16" y1="70" x2="104" y2="70" stroke="rgba(0,200,255,0.35)" stroke-width="1.5"/>
            <line x1="22" y1="48" x2="98" y2="48" stroke="rgba(0,200,255,0.2)" stroke-width="1"/>
            <line x1="22" y1="92" x2="98" y2="92" stroke="rgba(0,200,255,0.2)" stroke-width="1"/>
            <!-- single large eye (cyclops) -->
            <circle cx="60" cy="66" r="22" fill="#0a0f2e" stroke="rgba(0,200,255,0.6)" stroke-width="3"/>
            <circle cx="60" cy="66" r="16" fill="#00c8ff" opacity="0.15"/>
            <circle id="gb-eye-l" cx="60" cy="66" r="12" fill="#00c8ff"/>
            <circle cx="60" cy="66" r="8" fill="#0050aa"/>
            <circle cx="56" cy="62" r="3" fill="white" opacity="0.9"/>
            <!-- scan line across eye -->
            <rect x="38" y="64" width="44" height="2" rx="1" fill="rgba(0,200,255,0.4)"/>
            <!-- digital mouth -->
            <rect x="44" y="86" width="6" height="6" rx="2" fill="#00c8ff" opacity="0.8"/>
            <rect x="54" y="84" width="6" height="10" rx="2" fill="#00c8ff" opacity="0.9"/>
            <rect x="64" y="86" width="6" height="6" rx="2" fill="#00c8ff" opacity="0.8"/>
            <!-- antenna -->
            <line x1="60" y1="22" x2="60" y2="8" stroke="#00c8ff" stroke-width="3" stroke-linecap="round"/>
            <circle cx="60" cy="6" r="5" fill="#00c8ff"/>
            <circle cx="60" cy="6" r="3" fill="white" opacity="0.8">
              <animate attributeName="opacity" values="0.3;1;0.3" dur="1.5s" repeatCount="indefinite"/>
            </circle>
            <!-- jet ring bottom -->
            <ellipse cx="60" cy="118" rx="30" ry="8" fill="rgba(0,200,255,0.15)" stroke="rgba(0,200,255,0.4)" stroke-width="2"/>
            <!-- jet flames -->
            <ellipse cx="48" cy="124" rx="6" ry="12" fill="#00c8ff" opacity="0.7">
              <animate attributeName="ry" values="10;14;10" dur="0.4s" repeatCount="indefinite"/>
            </ellipse>
            <ellipse cx="60" cy="126" rx="7" ry="14" fill="#ffffff" opacity="0.5">
              <animate attributeName="ry" values="12;16;12" dur="0.35s" repeatCount="indefinite"/>
            </ellipse>
            <ellipse cx="72" cy="124" rx="6" ry="12" fill="#00c8ff" opacity="0.7">
              <animate attributeName="ry" values="10;14;10" dur="0.45s" repeatCount="indefinite"/>
            </ellipse>
            <!-- arms antenna-like -->
            <path class="arm-wave" d="M12 72 Q2 58 12 44" stroke="#1a3a6e" stroke-width="10" stroke-linecap="round" fill="none"/>
            <circle cx="12" cy="42" r="7" fill="#00c8ff" opacity="0.7"/>
            <path d="M108 72 Q118 80 110 94" stroke="#1a3a6e" stroke-width="10" stroke-linecap="round" fill="none"/>
            <circle cx="110" cy="96" r="7" fill="#00c8ff" opacity="0.7"/>
          </svg>`
        },
        {
          name: 'Paki', trait: 'Fun • Playful',
          color: 'rgba(79,110,247,0.45)',
          msgs: ['👍 Paki says let\'s GO!', '🎉 Travel is a party!', '😄 Fun trips only!'],
          eyeL: [44,68], eyeR: [78,68],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- chubby body -->
            <circle cx="60" cy="72" r="52" fill="#5577ff"/>
            <ellipse cx="60" cy="72" rx="24" ry="52" fill="none" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <line x1="8" y1="72" x2="112" y2="72" stroke="white" stroke-width="1.8" opacity="0.3"/>
            <ellipse cx="40" cy="50" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 50)"/>
            <!-- visor cap -->
            <ellipse cx="60" cy="22" rx="38" ry="11" fill="#c8962c"/>
            <rect x="22" y="18" width="76" height="14" rx="7" fill="#c8962c"/>
            <!-- visor brim -->
            <path d="M16 30 Q60 42 104 30" fill="#a07520" stroke="#a07520" stroke-width="1"/>
            <!-- eyes big happy -->
            <circle cx="44" cy="66" r="12" fill="white"/>
            <circle cx="78" cy="66" r="12" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="66" r="7" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="80" cy="66" r="7" fill="#1a1a2e"/>
            <circle cx="49" cy="63" r="2.5" fill="white"/>
            <circle cx="83" cy="63" r="2.5" fill="white"/>
            <!-- happy arc eyes (half shut joy) -->
            <!-- big grin -->
            <path d="M40 84 Q60 102 80 84" stroke="white" stroke-width="4" fill="none" stroke-linecap="round"/>
            <!-- teeth -->
            <path d="M48 87 Q60 98 72 87" fill="white" opacity="0.8"/>
            <!-- rosy cheeks big -->
            <ellipse cx="32" cy="80" rx="9" ry="6" fill="#ff7eb3" opacity="0.5"/>
            <ellipse cx="88" cy="80" rx="9" ry="6" fill="#ff7eb3" opacity="0.5"/>
            <!-- left arm thumbs up -->
            <path class="arm-wave" d="M8 80 Q-4 68 6 54" stroke="#4466ee" stroke-width="12" stroke-linecap="round" fill="none"/>
            <!-- thumb -->
            <circle cx="6" cy="52" r="9" fill="#FFD6A5"/>
            <path d="M2 52 Q6 44 10 52" stroke="#c8a070" stroke-width="2" fill="none" stroke-linecap="round"/>
            <!-- right arm up -->
            <path d="M112 80 Q122 66 112 52" stroke="#4466ee" stroke-width="12" stroke-linecap="round" fill="none"/>
            <circle cx="112" cy="50" r="9" fill="#FFD6A5"/>
            <!-- short stubby legs -->
            <rect x="41" y="120" width="17" height="20" rx="8" fill="#4466ee"/>
            <rect x="62" y="120" width="17" height="20" rx="8" fill="#4466ee"/>
            <rect x="32" y="132" width="28" height="11" rx="5" fill="#c8962c"/>
            <rect x="54" y="132" width="28" height="11" rx="5" fill="#c8962c"/>
          </svg>`
        },
        {
          name: 'Roami', trait: 'Relaxed • Easygoing',
          color: 'rgba(100,149,237,0.45)',
          msgs: ['☕ Travel + coffee = life', '😌 No rush, just vibes', '🛂 Passport ready!'],
          eyeL: [44,66], eyeR: [78,66],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- relaxed body, slightly lighter blue -->
            <circle cx="60" cy="70" r="50" fill="#6495ed"/>
            <ellipse cx="60" cy="70" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.28"/>
            <line x1="10" y1="70" x2="110" y2="70" stroke="white" stroke-width="1.8" opacity="0.28"/>
            <ellipse cx="40" cy="48" rx="11" ry="7" fill="white" opacity="0.12" transform="rotate(-25 40 48)"/>
            <!-- cozy scarf -->
            <path d="M26 92 Q60 108 94 92" fill="#e74c3c" stroke="#c0392b" stroke-width="2"/>
            <path d="M26 92 Q26 100 36 106 L40 98 Q60 108 80 98 L84 106 Q94 100 94 92" fill="#e74c3c"/>
            <!-- passport in left hand -->
            <rect x="4" y="82" width="18" height="24" rx="3" fill="#16213e"/>
            <rect x="6" y="84" width="14" height="20" rx="2" fill="#0f4c81"/>
            <ellipse cx="13" cy="94" rx="4" ry="5" fill="rgba(255,255,255,0.3)"/>
            <text x="6" y="100" font-size="7" fill="white" font-family="sans-serif" opacity="0.8">PASS</text>
            <!-- coffee cup in right hand -->
            <rect x="96" y="84" width="18" height="20" rx="4" fill="white"/>
            <rect x="98" y="86" width="14" height="14" rx="3" fill="#c8882a"/>
            <path d="M114 90 Q120 90 120 96 Q120 102 114 102" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"/>
            <!-- steam -->
            <path d="M101 84 Q103 78 101 72" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
            <path d="M107 82 Q109 76 107 70" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
            <!-- eyes half-closed relaxed -->
            <circle cx="44" cy="64" r="10" fill="white"/>
            <circle cx="78" cy="64" r="10" fill="white"/>
            <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#1a1a2e"/>
            <circle id="gb-eye-r" cx="80" cy="64" r="5.5" fill="#1a1a2e"/>
            <!-- half-lid -->
            <rect x="34" y="56" width="20" height="8" rx="4" fill="#6495ed" opacity="0.7"/>
            <rect x="68" y="56" width="20" height="8" rx="4" fill="#6495ed" opacity="0.7"/>
            <circle cx="48" cy="62" r="1.8" fill="white"/>
            <circle cx="82" cy="62" r="1.8" fill="white"/>
            <!-- relaxed smile -->
            <path d="M46 80 Q60 92 74 80" stroke="white" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            <!-- arms holding stuff -->
            <path d="M10 82 Q4 92 6 104" stroke="#5080cc" stroke-width="11" stroke-linecap="round" fill="none"/>
            <path d="M110 82 Q116 92 114 104" stroke="#5080cc" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs casual -->
            <rect x="43" y="118" width="15" height="22" rx="7.5" fill="#5080cc"/>
            <rect x="62" y="118" width="15" height="22" rx="7.5" fill="#5080cc"/>
            <rect x="34" y="132" width="24" height="10" rx="5" fill="#1a1a2e"/>
            <rect x="56" y="132" width="24" height="10" rx="5" fill="#1a1a2e"/>
          </svg>`
        },
        {
          name: 'Dasho', trait: 'Fast • Dynamic',
          color: 'rgba(255,160,0,0.5)',
          msgs: ['⚡ Speed is my thing!', '💨 Dasho never stops!', '🏃 First to the destination!'],
          eyeL: [50,64], eyeR: [78,64],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- speed lines behind -->
            <line x1="0" y1="50" x2="30" y2="50" stroke="rgba(255,160,0,0.4)" stroke-width="3" stroke-linecap="round"/>
            <line x1="0" y1="62" x2="20" y2="62" stroke="rgba(255,160,0,0.3)" stroke-width="2" stroke-linecap="round"/>
            <line x1="0" y1="74" x2="25" y2="74" stroke="rgba(255,160,0,0.4)" stroke-width="3" stroke-linecap="round"/>
            <line x1="0" y1="86" x2="15" y2="86" stroke="rgba(255,160,0,0.25)" stroke-width="2" stroke-linecap="round"/>
            <!-- body slightly tilted -->
            <g class="dasho-body" transform="rotate(-8,60,70)">
              <circle cx="60" cy="70" r="50" fill="#3355ff"/>
              <ellipse cx="60" cy="70" rx="23" ry="50" fill="none" stroke="white" stroke-width="1.8" opacity="0.28"/>
              <line x1="10" y1="70" x2="110" y2="70" stroke="white" stroke-width="1.8" opacity="0.28"/>
              <ellipse cx="40" cy="48" rx="11" ry="7" fill="white" opacity="0.1" transform="rotate(-25 40 48)"/>
              <!-- lightning bolt chest -->
              <polygon points="64,52 54,70 62,70 56,90 72,68 63,68" fill="#ffa500"/>
              <polygon points="64,52 54,70 62,70 56,90 72,68 63,68" fill="#ffd700" opacity="0.4"/>
              <!-- eyes (determined) -->
              <circle cx="44" cy="64" r="10" fill="white"/>
              <circle cx="76" cy="64" r="10" fill="white"/>
              <!-- angled brows -->
              <path d="M36 56 Q44 52 52 56" stroke="#1a1a2e" stroke-width="3" fill="none" stroke-linecap="round"/>
              <path d="M68 55 Q76 51 84 55" stroke="#1a1a2e" stroke-width="3" fill="none" stroke-linecap="round"/>
              <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#1a1a2e"/>
              <circle id="gb-eye-r" cx="78" cy="64" r="5.5" fill="#1a1a2e"/>
              <circle cx="48" cy="62" r="2" fill="white"/>
              <circle cx="80" cy="62" r="2" fill="white"/>
              <!-- determined smirk -->
              <path d="M48 80 Q62 86 74 78" stroke="white" stroke-width="3" fill="none" stroke-linecap="round"/>
            </g>
            <!-- arms dynamic run pose -->
            <path class="arm-wave" d="M8 60 Q-4 48 8 36" stroke="#2244dd" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="8" cy="34" r="8" fill="#FFD6A5"/>
            <path d="M108 84 Q120 96 112 108" stroke="#2244dd" stroke-width="11" stroke-linecap="round" fill="none"/>
            <!-- legs running -->
            <rect x="38" y="118" width="15" height="22" rx="7.5" fill="#2244dd" transform="rotate(-15 38 118)"/>
            <rect x="66" y="114" width="15" height="22" rx="7.5" fill="#2244dd" transform="rotate(12 66 114)"/>
            <!-- sneakers with speed marks -->
            <rect x="26" y="132" width="26" height="11" rx="5" fill="white"/>
            <rect x="26" y="132" width="10" height="11" rx="5" fill="#ffa500"/>
            <rect x="60" y="128" width="26" height="11" rx="5" fill="white"/>
            <rect x="60" y="128" width="10" height="11" rx="5" fill="#ffa500"/>
          </svg>`
        },
        {
          name: 'Lumi', trait: 'Calm • Inspiring',
          color: 'rgba(180,120,255,0.5)',
          msgs: ['✨ Inspire your journey!', '🌙 Lumi lights the way!', '💜 Travel with purpose!'],
          eyeL: [44,66], eyeR: [78,66],
          svg: `<svg width="96" height="120" viewBox="0 0 120 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- outer glow halo -->
            <circle cx="60" cy="68" r="56" fill="none" stroke="rgba(200,160,255,0.2)" stroke-width="8"/>
            <circle cx="60" cy="68" r="52" fill="none" stroke="rgba(200,160,255,0.15)" stroke-width="4"/>
            <!-- ground glow -->
            <ellipse cx="60" cy="128" rx="38" ry="10" fill="rgba(180,120,255,0.18)"/>
            <!-- lavender body -->
            <circle cx="60" cy="68" r="50" fill="#9b59b6"/>
            <circle cx="60" cy="68" r="46" fill="#a855d4" opacity="0.6"/>
            <ellipse cx="60" cy="68" rx="23" ry="50" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.8"/>
            <line x1="10" y1="68" x2="110" y2="68" stroke="rgba(255,255,255,0.35)" stroke-width="1.8"/>
            <ellipse cx="40" cy="46" rx="11" ry="7" fill="white" opacity="0.18" transform="rotate(-25 40 46)"/>
            <!-- sparkles around body -->
            <text x="8"  y="40" font-size="14" font-family="sans-serif">✦</text>
            <text x="100" y="36" font-size="12" font-family="sans-serif">✦</text>
            <text x="102" y="100" font-size="10" font-family="sans-serif">✦</text>
            <text x="6"  y="96" font-size="11" font-family="sans-serif">✦</text>
            <text x="50" y="12" font-size="10" font-family="sans-serif">✦</text>
            <!-- eyes calm soft -->
            <circle cx="44" cy="64" r="10" fill="white" opacity="0.95"/>
            <circle cx="78" cy="64" r="10" fill="white" opacity="0.95"/>
            <circle id="gb-eye-l" cx="46" cy="64" r="5.5" fill="#6c3483"/>
            <circle id="gb-eye-r" cx="80" cy="64" r="5.5" fill="#6c3483"/>
            <circle cx="48" cy="62" r="2" fill="white"/>
            <circle cx="82" cy="62" r="2" fill="white"/>
            <!-- serene smile -->
            <path d="M46 80 Q60 92 74 80" stroke="white" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            <!-- soft cheeks -->
            <ellipse cx="36" cy="76" rx="7" ry="4" fill="#d7aef5" opacity="0.6"/>
            <ellipse cx="84" cy="76" rx="7" ry="4" fill="#d7aef5" opacity="0.6"/>
            <!-- floating arms (no legs, hovers) -->
            <path class="arm-wave" d="M10 74 Q0 60 10 46" stroke="#7d3cad" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="10" cy="44" r="8" fill="#e8d5f5"/>
            <path d="M110 74 Q120 82 112 96" stroke="#7d3cad" stroke-width="11" stroke-linecap="round" fill="none"/>
            <circle cx="112" cy="98" r="8" fill="#e8d5f5"/>
            <!-- levitating, no feet — just fading bottom -->
            <ellipse cx="60" cy="118" rx="20" ry="6" fill="rgba(155,89,182,0.4)"/>
            <ellipse cx="60" cy="122" rx="14" ry="4" fill="rgba(155,89,182,0.2)"/>
          </svg>`
        }
      ];

      // Map each character to its exported PNG (public/mascot/*.png)
      CHARS.forEach(c => {
        const fname = c.name.toLowerCase();
        c.img = '/mascot/' + fname + '.png';
      });

      /* ══════════════════════════════════════════════
         PARTICLE ENGINE
         ══════════════════════════════════════════════ */
      const canvas = document.getElementById('gb-canvas');
      const ctx    = canvas.getContext('2d');

      function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
      resizeCanvas();
      window.addEventListener('resize', resizeCanvas, { passive: true });

      let mouseX = -300, mouseY = -300;

      /* ══════════════════════════════════════════════
         CURSOR TRAIL — animated sparkle particles
         ══════════════════════════════════════════════ */
      (function initTrail() {
        // no cursor trail on touch-only devices
        if (window.matchMedia('(hover: none) and (pointer: coarse)').matches) return;

        // Strip any transform from body/html that would make position:fixed relative to document
        document.body.style.transform = 'none';
        document.documentElement.style.transform = 'none';

        const tc = document.createElement('canvas');
        // Use explicit top/left/width/height instead of inset so scroll offset can be applied
        tc.style.position = 'fixed';
        tc.style.top = '0';
        tc.style.left = '0';
        tc.style.pointerEvents = 'none';
        tc.style.zIndex = '99997';
        // Append to documentElement (<html>) to escape any body transform
        document.documentElement.appendChild(tc);
        const tx = tc.getContext('2d');

        function resize() {
          tc.width  = window.innerWidth;
          tc.height = window.innerHeight;
          tc.style.width  = window.innerWidth  + 'px';
          tc.style.height = window.innerHeight + 'px';
        }
        resize();
        window.addEventListener('resize', resize);

        let cx = -300, cy = -300;
        document.addEventListener('mousemove', e => { cx = e.clientX; cy = e.clientY; mouseX = cx; mouseY = cy; });

        const sparks = [];
        const COLORS = [
          '#a78bfa','#818cf8','#60a5fa',
          '#f472b6','#fb7185','#fbbf24',
          '#34d399','#38bdf8',
        ];

        // draw a 4-point star (cross shape)
        function drawStar4(ctx, x, y, r, rot) {
          ctx.save();
          ctx.translate(x, y);
          ctx.rotate(rot);
          ctx.beginPath();
          const arms = 4, outer = r, inner = r * 0.2;
          for (let i = 0; i < arms * 2; i++) {
            const a = (i * Math.PI) / arms;
            const rad = i % 2 === 0 ? outer : inner;
            i === 0 ? ctx.moveTo(Math.cos(a)*rad, Math.sin(a)*rad)
                    : ctx.lineTo(Math.cos(a)*rad, Math.sin(a)*rad);
          }
          ctx.closePath();
          ctx.fill();
          ctx.restore();
        }

        // tiny cross/plus
        function drawCross(ctx, x, y, r, rot) {
          ctx.save();
          ctx.translate(x, y);
          ctx.rotate(rot);
          ctx.fillRect(-r * 0.18, -r, r * 0.36, r * 2);
          ctx.fillRect(-r, -r * 0.18, r * 2, r * 0.36);
          ctx.restore();
        }

        let spawnAcc = 0;
        let lastTime = 0;

        (function loop(now) {
          requestAnimationFrame(loop);
          const dt = Math.min(now - lastTime, 50); lastTime = now;

          tx.clearRect(0, 0, tc.width, tc.height);

          // spawn new sparks based on time (not just mouse move — so they pulse)
          spawnAcc += dt;
          if (spawnAcc > 22 && cx > 0) {
            spawnAcc = 0;
            const count = Math.random() < 0.3 ? 2 : 1;
            for (let i = 0; i < count; i++) {
              const angle = Math.random() * Math.PI * 2;
              const spread = Math.random() * 6;
              sparks.push({
                x: cx + Math.cos(angle) * spread,
                y: cy + Math.sin(angle) * spread,
                vx: (Math.random() - 0.5) * 1.4,
                vy: (Math.random() - 0.5) * 1.4 - 0.6,
                size: Math.random() * 7 + 3,
                life: 1,
                decay: Math.random() * 0.022 + 0.014,
                color: COLORS[Math.floor(Math.random() * COLORS.length)],
                rot: Math.random() * Math.PI,
                rotV: (Math.random() - 0.5) * 0.12,
                type: Math.random() < 0.55 ? 'star4' : 'cross',
              });
            }
          }

          // update & draw
          for (let i = sparks.length - 1; i >= 0; i--) {
            const s = sparks[i];
            s.x  += s.vx;
            s.y  += s.vy;
            s.vy += 0.018;          // gentle gravity drift
            s.rot += s.rotV;
            s.life -= s.decay;
            if (s.life <= 0) { sparks.splice(i, 1); continue; }

            tx.globalAlpha = s.life * s.life;  // ease-out fade
            tx.fillStyle = s.color;

            // glow pass
            tx.shadowColor = s.color;
            tx.shadowBlur  = 10 * s.life;

            const r = s.size * s.life;
            if (s.type === 'star4') drawStar4(tx, s.x, s.y, r, s.rot);
            else                    drawCross(tx, s.x, s.y, r, s.rot);

            tx.shadowBlur = 0;
          }
          tx.globalAlpha = 1;
        })(0);
      })();

      /* particle palette per character */
      const palettes = {
        Orbit:  ['#4f6ef7','#ff6b35','#ffffff','#ffe066','#a78bfa'],
        Jetto:  ['#20b2aa','#d4a853','#ffffff','#34d399','#60a5fa'],
        Nomio:  ['#4a6cf7','#ff8c00','#ffffff','#ffd700','#c084fc'],
        Trailix:['#1a9e9e','#e67e22','#ffffff','#4ecdc4','#a3e635'],
        Guidee: ['#4f6ef7','#ff6b35','#ffffff','#fbbf24','#f472b6'],
        Voyaj:  ['#00c8ff','#0050aa','#ffffff','#7dd3fc','#38bdf8'],
        Paki:   ['#5577ff','#c8962c','#ffffff','#ff7eb3','#fde68a'],
        Roami:  ['#6495ed','#e74c3c','#ffffff','#fbbf24','#86efac'],
        Dasho:  ['#3355ff','#ffa500','#ffffff','#ffd700','#f87171'],
        Lumi:   ['#9b59b6','#d7aef5','#ffffff','#f0abfc','#c4b5fd'],
      };

      const shadowColors = {
        Orbit:'rgba(79,110,247,0.4)', Jetto:'rgba(32,178,170,0.4)',
        Nomio:'rgba(79,110,247,0.4)', Trailix:'rgba(26,158,158,0.4)',
        Guidee:'rgba(79,110,247,0.4)', Voyaj:'rgba(0,200,255,0.4)',
        Paki:'rgba(85,119,255,0.4)', Roami:'rgba(100,149,237,0.4)',
        Dasho:'rgba(255,160,0,0.4)', Lumi:'rgba(180,120,255,0.4)'
      };

      /* Pick a random character per session (stored in sessionStorage) */
      let charIdx = parseInt(sessionStorage.getItem('tg_char') || '-1');
      if (charIdx < 0) {
        charIdx = Math.floor(Math.random() * CHARS.length);
      }

      const characterEl = document.getElementById('gb-character-art');
      const shadowEl = document.querySelector('.gb-shadow');
      const cycleBtn = document.getElementById('gb-cycle');
      let char = null;
      let msgs = [];
      let pal = palettes.Orbit;

      function applyCharacter(index, announce) {
        charIdx = (index + CHARS.length) % CHARS.length;
        char = CHARS[charIdx];
        // prefer exported PNG if available, fall back to inline svg
        if (char.img) {
          characterEl.innerHTML = `<img class="gb-pic" src="${char.img}" alt="${char.name}">`;
        } else {
          characterEl.innerHTML = char.svg || '';
        }
        msgs = char.msgs.concat(['✈️ Need trip ideas?','🌍 Where to next?','🤖 Ask me anything!','👆 Double-click me!','🗺️ Plan your adventure!','⭐ Your next trip awaits!']);
        pal = palettes[char.name] || palettes.Orbit;
        shadowEl.style.setProperty('--gb-shadow-color', shadowColors[char.name]||'rgba(79,110,247,0.4)');
        sessionStorage.setItem('tg_char', String(charIdx));
        // update chat header if open
        const _chatAvatar = document.getElementById('gbc-avatar');
        const _chatName   = document.getElementById('gbc-name');
        const _chatTrait  = document.getElementById('gbc-trait');
        if (_chatAvatar) { _chatAvatar.src = char.img||''; _chatName.textContent = char.name; _chatTrait.textContent = char.trait; }
        if (announce) {
          showBubble(`Now featuring ${char.name}`, 2200);
        }
      }

      applyCharacter(charIdx, false);
      cycleBtn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        applyCharacter(charIdx + 1, true);
      });

      /* ── particle pool ── */
      let particles = [];

      function randRange(a, b) { return a + Math.random() * (b - a); }
      function randEl(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

      /* shapes: 'circle' | 'star' | 'ring' | 'diamond' | 'trail' */
      function spawnParticle(x, y, kind) {
        const angle = randRange(0, Math.PI * 2);
        const speed = kind === 'trail' ? randRange(0.4, 1.4) : randRange(1.2, 4.2);
        return {
          x, y,
          vx: Math.cos(angle) * speed * (kind === 'trail' ? 1 : randRange(.5,1)),
          vy: Math.sin(angle) * speed - (kind === 'trail' ? 0 : randRange(.5, 2)),
          life: 1,
          decay: kind === 'trail' ? randRange(.012, .022) : randRange(.018, .04),
          size: randRange(3, kind === 'burst' ? 9 : 6),
          color: randEl(pal),
          shape: randEl(['circle','star','diamond','ring']),
          rot: randRange(0, Math.PI * 2),
          rotV: randRange(-.08, .08),
          kind,
          gravity: kind === 'trail' ? 0.02 : 0.06,
        };
      }

      /* Ambient idle particles — drift up from bot position */
      let idleTimer = 0;
      function spawnIdle(botX, botY) {
        const p = spawnParticle(
          botX + randRange(5, BOT_W - 5),
          botY + randRange(10, BOT_H * .7),
          'idle'
        );
        p.vx = randRange(-.35, .35);
        p.vy = randRange(-.9, -.25);
        p.decay = randRange(.009, .018);
        p.size  = randRange(1.5, 4);
        p.gravity = 0;
        particles.push(p);
      }

      /* Trail particles while roaming */
      function spawnTrail(botX, botY) {
        const p = spawnParticle(botX + randRange(10, BOT_W - 10), botY + randRange(10, BOT_H - 10), 'trail');
        p.size = randRange(2, 5);
        particles.push(p);
      }

      /* Burst on double-click */
      function spawnBurst(botX, botY) {
        const cx = botX + BOT_W/2, cy = botY + BOT_H * .45;
        for (let i = 0; i < 45; i++) {
          const p = spawnParticle(cx, cy, 'burst');
          particles.push(p);
        }
        for (let i = 0; i < 8; i++) {
          const p = spawnParticle(cx, cy, 'burst');
          p.shape = 'star'; p.size = randRange(6, 12);
          p.vx *= 1.6; p.vy *= 1.6; p.decay = .012;
          particles.push(p);
        }
      }

      /* Hover sparkle trickle */
      let hoverActive = false;
      function spawnHover(botX, botY) {
        const p = spawnParticle(botX + randRange(0, BOT_W), botY + randRange(0, BOT_H), 'hover');
        p.vx = randRange(-.5, .5); p.vy = randRange(-1.6, -.4);
        p.size = randRange(1.5, 4.5); p.decay = .022; p.gravity = 0;
        particles.push(p);
      }

      /* ── draw helpers ── */
      function drawStar(cx, cy, r, rot) {
        ctx.beginPath();
        for (let i = 0; i < 10; i++) {
          const a = rot + (i * Math.PI) / 5;
          const rr = i % 2 === 0 ? r : r * .45;
          i === 0 ? ctx.moveTo(cx + Math.cos(a)*rr, cy + Math.sin(a)*rr)
                  : ctx.lineTo(cx + Math.cos(a)*rr, cy + Math.sin(a)*rr);
        }
        ctx.closePath(); ctx.fill();
      }
      function drawDiamond(cx, cy, r, rot) {
        ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot);
        ctx.beginPath();
        ctx.moveTo(0,-r); ctx.lineTo(r*.55,0); ctx.lineTo(0,r); ctx.lineTo(-r*.55,0);
        ctx.closePath(); ctx.fill();
        ctx.restore();
      }

      /* ── particle cap — never exceed this many alive ── */
      const MAX_PARTICLES = 120;

      /* ── main loop ── */
      let lastT = 0, frameN = 0;
      function loop(t) {
        requestAnimationFrame(loop);
        const dt = Math.min(t - lastT, 50); lastT = t; frameN++;

        ctx.clearRect(0, 0, canvas.width, canvas.height);


        const bot    = document.getElementById('globe-bot');
        const rect   = bot.getBoundingClientRect();
        const bx = rect.left, by = rect.top;

        /* spawn idle every 7 frames, only if under cap */
        if (frameN % 7 === 0 && particles.length < MAX_PARTICLES) spawnIdle(bx, by);

        /* spawn trail when roaming */
        if (bot.classList.contains('roaming') && frameN % 4 === 0 && particles.length < MAX_PARTICLES) spawnTrail(bx, by);

        /* hover sparkle */
        if (hoverActive && frameN % 5 === 0 && particles.length < MAX_PARTICLES) spawnHover(bx, by);

        /* update & draw */
        particles = particles.filter(p => p.life > 0);
        for (const p of particles) {
          p.x  += p.vx; p.y += p.vy;
          p.vy += p.gravity;
          p.rot += p.rotV;
          p.life -= p.decay;
          if (p.life <= 0) continue;

          const alpha = Math.min(p.life, .95);
          ctx.globalAlpha = alpha;
          ctx.fillStyle   = p.color;
          ctx.strokeStyle = p.color;

          switch (p.shape) {
            case 'circle':
              ctx.beginPath();
              ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI*2);
              ctx.fill();
              break;
            case 'star':
              drawStar(p.x, p.y, p.size * p.life, p.rot);
              break;
            case 'diamond':
              drawDiamond(p.x, p.y, p.size * p.life, p.rot);
              break;
            case 'ring':
              ctx.beginPath();
              ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI*2);
              ctx.lineWidth = 1.5 * p.life;
              ctx.stroke();
              break;
          }
        }
        ctx.globalAlpha = 1;
      }
      requestAnimationFrame(loop);

      /* ══════════════════════════════════════════════
         BEHAVIOUR
         ══════════════════════════════════════════════ */
      const bot    = document.getElementById('globe-bot');
      const bubble = document.getElementById('gb-bubble');
      const tripUrl = window._mascotCfg.tripUrl;

      const isMobile = window.innerWidth <= 768;
      const BOT_W = isMobile ? 80 : 110;
      const BOT_H = isMobile ? 110 : 150;
      const BOT_MARGIN_X = isMobile ? 12 : 24;
      const BOT_MARGIN_Y = isMobile ? 20 : 24;

      let posX = window.innerWidth  - BOT_W - BOT_MARGIN_X;
      let posY = window.innerHeight - BOT_H - BOT_MARGIN_Y;
      let dragging = false, dragOffX = 0, dragOffY = 0;
      let roaming = false, roamTimer = null, roamRAF = null;
      let bubbleTimer = null;
      let userHasDragged = false; // track manual drag so we can snap back on scroll

      function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

      function snapToDefault() {
        posX = window.innerWidth  - BOT_W - BOT_MARGIN_X;
        posY = window.innerHeight - BOT_H - BOT_MARGIN_Y;
      }

      function applyPos() {
        posX = clamp(posX, 8, window.innerWidth  - BOT_W - 8);
        posY = clamp(posY, 8, window.innerHeight - BOT_H - 8);
        bot.style.left = posX + 'px'; bot.style.top = posY + 'px';
        bot.style.right = 'auto'; bot.style.bottom = 'auto';
      }
      applyPos();

      // Re-anchor to bottom-right on scroll (handles mobile URL-bar collapse)
      window.addEventListener('scroll', function () {
        if (!dragging && !userHasDragged && !roaming) {
          snapToDefault(); applyPos(); positionChat();
        }
      }, { passive: true });

      function showBubble(text, ms) {
        clearTimeout(bubbleTimer);
        bubble.querySelector('span').textContent = text;
        bubble.classList.add('visible');
        if (ms) bubbleTimer = setTimeout(() => bubble.classList.remove('visible'), ms);
      }

      setInterval(() => {
        if (!dragging && !roaming)
          showBubble(msgs[Math.floor(Math.random() * msgs.length)], 3400);
      }, 11000);
      setTimeout(() => showBubble('👋 Double-click me!', 4200), 3000);
      setInterval(() => {
        if (!dragging && !roaming && document.visibilityState === 'visible') {
          applyCharacter(charIdx + 1, false);
        }
      }, 30000);

      /* hover sparkle toggle */
      bot.addEventListener('mouseenter', () => { hoverActive = true; });
      bot.addEventListener('mouseleave', () => { hoverActive = false; });

      /* drag */
      bot.addEventListener('mousedown', e => {
        if (e.target.closest('.gb-cycle-btn')) return;
        dragging = true; userHasDragged = true; stopRoaming();
        dragOffX = e.clientX - posX; dragOffY = e.clientY - posY;
        bot.style.cursor = 'grabbing'; bot.style.animation = 'none';
        bubble.classList.remove('visible');
        e.preventDefault();
      });
      document.addEventListener('mousemove', e => {
        if (!dragging) return;
        posX = clamp(e.clientX - dragOffX, 8, window.innerWidth  - BOT_W - 8);
        posY = clamp(e.clientY - dragOffY, 8, window.innerHeight - BOT_H - 8);
        applyPos();
        positionChat();
        /* drag trail */
        if (Math.random() < .6) {
          const p = spawnParticle(posX + 48, posY + 60, 'trail');
          p.vx = randRange(-1,1); p.vy = randRange(-1,.5);
          p.size = randRange(3,8); p.gravity = .03;
          particles.push(p);
        }
      });
      document.addEventListener('mouseup', () => {
        if (!dragging) return;
        dragging = false;
        bot.style.cursor = 'grab';
        bot.style.animation = 'gb-float 3.2s ease-in-out infinite';
        scheduleRoam();
      });

      /* ── Touch drag ── */
      bot.addEventListener('touchstart', e => {
        if (e.target.closest('.gb-cycle-btn')) return;
        dragging = true; userHasDragged = true; stopRoaming();
        const t = e.touches[0];
        dragOffX = t.clientX - posX; dragOffY = t.clientY - posY;
        bot.style.animation = 'none';
        bubble.classList.remove('visible');
      }, { passive: true });

      document.addEventListener('touchmove', e => {
        if (!dragging) return;
        const t = e.touches[0];
        posX = clamp(t.clientX - dragOffX, 8, window.innerWidth  - BOT_W - 8);
        posY = clamp(t.clientY - dragOffY, 8, window.innerHeight - BOT_H - 8);
        applyPos(); positionChat();
      }, { passive: true });

      document.addEventListener('touchend', () => {
        if (!dragging) return;
        dragging = false;
        bot.style.animation = 'gb-float 3.2s ease-in-out infinite';
        scheduleRoam();
      }, { passive: true });

      /* ── Double-tap for touch ── */
      let lastTap = 0;
      bot.addEventListener('touchend', e => {
        const now = Date.now();
        if (now - lastTap < 320) {
          e.preventDefault();
          stopRoaming();
          bot.classList.remove('excited'); void bot.offsetWidth;
          bot.classList.add('excited');
          spawnBurst(posX, posY);
          if (chatOpen) closeChat();
          else { showBubble('💬 Let\'s plan your trip!', 2000); openChat(); }
        }
        lastTap = now;
      });

      /* ── Chat window logic ── */
      const chatEl    = document.getElementById('gb-chat');
      const chatMsgs  = document.getElementById('gbc-messages');
      const chatForm  = document.getElementById('gbc-form');
      const chatInput = document.getElementById('gbc-input');
      const chatClose = document.getElementById('gbc-close');
      const chatAvatar= document.getElementById('gbc-avatar');
      const chatName  = document.getElementById('gbc-name');
      const chatTrait = document.getElementById('gbc-trait');
      let chatOpen = false;
      let chatBusy = false;

      function positionChat() {
        if (!chatOpen) return;
        const CHAT_H = 540;
        const CHAT_W = 350;
        const GAP = 14;
        const bRect = bot.getBoundingClientRect();
        const spaceAbove = bRect.top;
        const spaceBelow = window.innerHeight - bRect.bottom;

        // horizontal: align right edge with mascot right, clamp to viewport
        let rightVal = window.innerWidth - bRect.right;
        rightVal = Math.max(8, Math.min(rightVal, window.innerWidth - CHAT_W - 8));

        chatEl.style.left  = 'auto';
        chatEl.style.right = rightVal + 'px';

        if (spaceAbove >= CHAT_H * 0.45 || spaceAbove > spaceBelow) {
          // show ABOVE mascot
          chatEl.style.top    = 'auto';
          chatEl.style.bottom = (window.innerHeight - bRect.top + GAP) + 'px';
        } else {
          // flip BELOW mascot
          chatEl.style.bottom = 'auto';
          chatEl.style.top    = (bRect.bottom + GAP) + 'px';
        }
      }

      function openChat() {
        chatAvatar.src  = char.img || '';
        chatName.textContent  = char.name;
        chatTrait.textContent = char.trait;
        chatEl.classList.add('open');
        chatEl.setAttribute('aria-hidden','false');
        chatOpen = true;
        positionChat();
        // greet if empty
        if (chatMsgs.children.length === 0) {
          const greet = char.msgs[0] || '👋 Hi!';
          setTimeout(() => {
            addBotMsg('👋 Hey! I\'m ' + char.name + ' — ' + char.trait + '!');
          }, 300);
          setTimeout(() => {
            addBotMsg(greet + ' Tell me your dream trip and I\'ll find the perfect options! ✈️');
          }, 900);
          setTimeout(() => {
            // quick reply chips
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;padding:2px 0;animation:gbc-pop .3s ease both';
            const chips = ['🏖️ Beach trip','🏔️ Adventure','🌆 City break','💰 Budget trip'];
            chips.forEach(c => {
              const chip = document.createElement('button');
              chip.type = 'button';
              chip.textContent = c;
              chip.style.cssText = 'background:rgba(79,110,247,.12);border:1px solid rgba(79,110,247,.28);border-radius:20px;padding:5px 12px;color:#93c5fd;font-family:Inter,sans-serif;font-size:11.5px;font-weight:600;cursor:pointer;transition:background .18s,transform .18s;white-space:nowrap';
              chip.onmouseenter = () => { chip.style.background='rgba(79,110,247,.25)'; chip.style.transform='scale(1.05)'; };
              chip.onmouseleave = () => { chip.style.background='rgba(79,110,247,.12)'; chip.style.transform=''; };
              chip.onclick = () => { chatInput.value = c.replace(/^[^\s]+\s/,''); row.remove(); sendQuery(c); };
              row.appendChild(chip);
            });
            chatMsgs.appendChild(row);
            chatMsgs.scrollTop = chatMsgs.scrollHeight;
          }, 1400);
        }
        setTimeout(() => chatInput.focus(), 320);
      }

      function closeChat() {
        chatEl.classList.remove('open');
        chatEl.setAttribute('aria-hidden','true');
        chatOpen = false;
      }

      function addMsg(role, html) {
        const row = document.createElement('div');
        row.className = 'gbc-msg ' + role;
        if (role === 'bot') {
          const av = document.createElement('img');
          av.className = 'gbc-msg-avatar';
          av.src = char.img || '';
          av.alt = char.name;
          row.appendChild(av);
        }
        const bubble = document.createElement('div');
        bubble.className = 'gbc-msg-bubble';
        bubble.innerHTML = html;
        row.appendChild(bubble);
        chatMsgs.appendChild(row);
        chatMsgs.scrollTop = chatMsgs.scrollHeight;
        return bubble;
      }

      function addBotMsg(text) { addMsg('bot', escHtml(text)); }

      function escHtml(t) {
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      }

      function showTyping() {
        const row = document.createElement('div');
        row.className = 'gbc-msg bot'; row.id = 'gbc-typing-row';
        const av = document.createElement('img');
        av.className = 'gbc-msg-avatar'; av.src = char.img||''; av.alt = char.name;
        row.appendChild(av);
        const t = document.createElement('div');
        t.className = 'gbc-typing';
        t.innerHTML = '<span></span><span></span><span></span>';
        row.appendChild(t);
        chatMsgs.appendChild(row);
        chatMsgs.scrollTop = chatMsgs.scrollHeight;
      }

      function removeTyping() {
        const t = document.getElementById('gbc-typing-row');
        if (t) t.remove();
      }

      async function sendQuery(query) {
        if (chatBusy) return;
        chatBusy = true;
        chatInput.disabled = true;
        document.querySelector('.gbc-send').disabled = true;

        addMsg('user', escHtml(query));
        showTyping();

        try {
          const fd = new FormData();
          fd.append('query', query);
          const res = await fetch(window._mascotCfg.planUrl, { method:'POST', body: fd });
          const data = await res.json();
          removeTyping();

          if (!data.success) {
            addBotMsg('😅 ' + (data.error || 'Something went wrong, try again!'));
          } else {
            // explanation
            addBotMsg(data.explanation || '✨ Here\'s what I found for you!');
            // recommendations
            if (data.recommendations && data.recommendations.length > 0) {
              const icons = ['🌍','🗺️','✈️','🏖️','🏔️','🌅'];
              const bubble = addMsg('bot', '');
              bubble.style.maxWidth = '100%';
              bubble.style.background = 'transparent';
              bubble.style.border = 'none';
              bubble.style.padding = '0';
              bubble.style.boxShadow = 'none';
              data.recommendations.slice(0,3).forEach((rec, i) => {
                const v = rec.voyage;
                const card = document.createElement('div');
                card.className = 'gbc-rec';
                card.style.animationDelay = (i * 120) + 'ms';
                const dest = escHtml(v.destination || v.title || 'Destination');
                const tripLink = v.slug ? `/voyages/${v.slug}` : `/voyages/voyage-${v.id}`;
                card.innerHTML = `<div class="gbc-rec-icon">${icons[i % icons.length]}</div>`
                  + `<div class="gbc-rec-title">${dest}</div>`
                  + `<div class="gbc-rec-reason">${escHtml(rec.reason||'')}</div>`
                  + (rec.estimated_price ? `<div class="gbc-rec-price">~$${Number(rec.estimated_price).toLocaleString()}</div>` : '')
                  + `<a href="${tripLink}" class="gbc-rec-btn">View Trip →</a>`;
                bubble.appendChild(card);
              });
              chatMsgs.scrollTop = chatMsgs.scrollHeight;
            }
            // follow-up nudge with delay
            setTimeout(() => addBotMsg('Want me to refine this? Tell me your budget, travel dates, or vibe! 😊'), 1000);
          }
        } catch(err) {
          removeTyping();
          addBotMsg('😓 Couldn\'t reach the planner right now. Try again in a moment!');
        }

        chatBusy = false;
        chatInput.disabled = false;
        document.querySelector('.gbc-send').disabled = false;
        chatInput.focus();
      }

      chatForm.addEventListener('submit', e => {
        e.preventDefault();
        const q = chatInput.value.trim();
        if (!q || chatBusy) return;
        chatInput.value = '';
        sendQuery(q);
      });

      chatClose.addEventListener('click', closeChat);

      /* double-click → burst → open chat */
      bot.addEventListener('dblclick', e => {
        e.preventDefault(); stopRoaming();
        bot.classList.remove('excited'); void bot.offsetWidth;
        bot.classList.add('excited');
        spawnBurst(posX, posY);
        if (chatOpen) {
          closeChat();
        } else {
          showBubble('💬 Let\'s plan your trip!', 2000);
          openChat();
        }
      });

      /* roam */
      function scheduleRoam() {
        clearTimeout(roamTimer);
        roamTimer = setTimeout(startRoam, 7000 + Math.random() * 7000);
      }
      function startRoam() {
        roaming = true; bot.classList.add('roaming'); bot.style.animation = 'none';
        const tx = clamp(randRange(40, window.innerWidth  - BOT_W - 16), 8, window.innerWidth  - BOT_W - 8);
        const ty = clamp(randRange(80, window.innerHeight - BOT_H - 16), 60, window.innerHeight - BOT_H - 8);
        const sx = posX, sy = posY;
        const dur = Math.max(1800, Math.hypot(tx-sx, ty-sy) * 3.6);
        const t0  = performance.now();
        function ease(t){ return t<.5?2*t*t:-1+(4-2*t)*t; }
        (function step(now){
          if (!roaming) return;
          const p = Math.min((now-t0)/dur,1), ep=ease(p);
          posX = sx+(tx-sx)*ep; posY = sy+(ty-sy)*ep; applyPos(); positionChat();
          if (p<1) roamRAF = requestAnimationFrame(step);
          else { stopRoaming(); scheduleRoam(); }
        })(t0);
      }
      function stopRoaming() {
        roaming = false; clearTimeout(roamTimer); cancelAnimationFrame(roamRAF);
        bot.classList.remove('roaming');
        bot.style.animation = 'gb-float 3.2s ease-in-out infinite';
      }
      scheduleRoam();

      /* eye tracking — throttled to ~30fps via rAF flag */
      let eyeRAF = false;
      document.addEventListener('mousemove', e => {
        if (dragging || eyeRAF) return;
        eyeRAF = true;
        requestAnimationFrame(() => {
          eyeRAF = false;
          const eyeL = document.getElementById('gb-eye-l');
          const eyeR = document.getElementById('gb-eye-r');
          if (!eyeL) return;
          const rect = bot.getBoundingClientRect();
          const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
          const ang = Math.atan2(e.clientY-cy, e.clientX-cx);
          const dx = Math.cos(ang)*2.8, dy = Math.sin(ang)*2.8;
          const [blx,bly] = char.eyeL, [brx,bry] = char.eyeR;
          eyeL.setAttribute('cx', blx+dx); eyeL.setAttribute('cy', bly+dy);
          eyeR.setAttribute('cx', brx+dx); eyeR.setAttribute('cy', bry+dy);
        });
      }, { passive: true });

      window.addEventListener('resize', () => {
        if (!userHasDragged) snapToDefault();
        applyPos(); positionChat();
      }, { passive: true });

      /* pause canvas work when tab is hidden */
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) particles.length = 0;
      });
    })();
