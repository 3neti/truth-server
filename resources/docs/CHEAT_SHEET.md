# Cheat Sheet

```bash
truncate -s0 storage/logs/laravel.log
composer pub-stubs
php artisan election:setup-precinct --fresh
echo "317537" | php artisan election:tally-votes
php artisan election:read-vote BAL-000 A1
php artisan election:read-vote BAL-000 B1
php artisan election:read-vote BAL-000 C1
php artisan election:read-vote BAL-000 C2
php artisan election:read-vote BAL-000 C3
php artisan election:read-vote BAL-000 C4
php artisan election:read-vote BAL-000 C5
php artisan election:read-vote BAL-000 C6
php artisan election:read-vote BAL-000 C7
php artisan election:read-vote BAL-000 C8
php artisan election:read-vote BAL-000 C9
php artisan election:read-vote BAL-000 C10
php artisan election:read-vote BAL-000 C11
php artisan election:read-vote BAL-000 C12
php artisan election:read-vote BAL-000 D1
php artisan election:read-vote BAL-000 E1
php artisan election:read-vote BAL-000 F1
php artisan election:read-vote BAL-000 F2
php artisan election:read-vote BAL-000 G1
php artisan election:read-vote BAL-000 H1
php artisan election:read-vote BAL-000 J1
php artisan election:read-vote BAL-000 J2
php artisan election:read-vote BAL-000 J3
php artisan election:read-vote BAL-000 J4
php artisan election:read-vote BAL-000 J5
php artisan election:read-vote BAL-000 J6
php artisan election:read-vote BAL-000 J7
php artisan election:read-vote BAL-000 J8
php artisan election:read-vote BAL-000 H1
php artisan election:finalize-ballot BAL-000
php artisan election:cast-ballot "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018,GG_016,BC_015,MD_009,WS_007,MA_035,SB_006,FP_038,OS_028,MF_003;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008"
php artisan election:cast-ballot "BAL-001|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:DP_004,BDT_005;REPRESENTATIVE-ILN-1:JF_001;MAYOR-ILN-CURRIMAO:EW_003;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
php artisan election:cast-ballot "BAL-002|PRESIDENT:AJ_006;VICE-PRESIDENT:AH_006;SENATOR:JC_045,MA_035,JM_027,BC_015,RG_029,FP_038,AA_018,JD_001,KR_011,OS_028,EB_022,A_044;REPRESENTATIVE-PARTY-LIST:TENET_040"
php artisan election:cast-ballot "BAL-002|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:DK_003;BOARD-MEMBER-ILN:HB_002,RW_001;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:BC_001;VICE-MAYOR-ILN-CURRIMAO:JL_002;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
php artisan election:cast-ballot "BAL-003|PRESIDENT:DW_003;VICE-PRESIDENT:RDJ_005;SENATOR:JG_017,MF_003,TH_047,SH_030,MA_035,JR_008,CB_025,SB_006,EG_036,DC_019,RM_040,RM_024;REPRESENTATIVE-PARTY-LIST:LUCA_148"
php artisan election:cast-ballot "BAL-003|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:NW_001;BOARD-MEMBER-ILN:RW_001,DJ_006;REPRESENTATIVE-ILN-1:RW_002;MAYOR-ILN-CURRIMAO:LJ_002;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
echo "BAL-004|PRESIDENT:SJ_002;VICE-PRESIDENT:TH_001;SENATOR:CB_025,AA_018,SH_030,ATJ_041,RM_024,KR_011,AG_046,CE_023,ZS_014,BC_049,CB_005,PP_039;REPRESENTATIVE-PARTY-LIST:THE_MARTIAN_044" | php artisan election:cast
echo "BAL-004|GOVERNOR-ILN:RP_003;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:RW_003,DP_004;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:LJ_002;VICE-MAYOR-ILN-CURRIMAO:JF_003;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008" | php artisan election:cast
echo "BAL-005|PRESIDENT:AJ_006;VICE-PRESIDENT:RDJ_005;SENATOR:BC_015,A_044,MR_021,JL_004,HM_050,MF_003,LN_048,TS_026,AA_018,CB_005,ZS_014,TC_037;REPRESENTATIVE-PARTY-LIST:THE_GREEN_MILE_081" | php artisan election:cast
echo "BAL-005|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:JH_004;BOARD-MEMBER-ILN:AD_007,DJ_006;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:BC_001;VICE-MAYOR-ILN-CURRIMAO:JL_002;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008" | php artisan election:cast
php artisan election:attest-return BEI:uuid-juan:signature123
php artisan election:attest-return BEI:uuid-maria:signature456
echo "BEI:uuid-pedro:signature789" | php artisan election:attest-return
php artisan election:record-statistics '{"watchers_count":5,"registered_voters_count":800,"actual_voters_count":700,"ballots_in_box_count":695,"unused_ballots_count":105}'
echo '{"watchers_count":6,"registered_voters_count":801,"actual_voters_count":701,"ballots_in_box_count":696,"unused_ballots_count":106}' | php artisan election:record-statistics
php artisan election:wrapup-voting
```
```bash
# Election API Simulation via cURL
# Domain: http://truth.test

# 1. Setup Precinct
curl -X POST http://truth.test/api/election/setup-precinct 

# 2. Read Votes for BAL-000
for key in A1 B1 C1 C2 C3 C4 C5 C6 C7 C8 C9 C10 C11 C12 D1 E1 F1 F2 G1 H1 J1 J2 J3 J4 J5 J6 J7 J8 H1; do
  curl -X POST http://truth.test/api/election/read-vote \
    -H "Content-Type: application/json" \
    -d '{"code":"BAL-000", "key":"'$key'"}'
  echo "Read vote: $key"
done

# 3. Finalize Ballot BAL-000
curl -X POST http://truth.test/api/election/finalize-ballot \
  -H "Content-Type: application/json" \
  -d '{"code":"BAL-000"}'

# 4. Cast Ballots (BAL-001 to BAL-005)
ballots=(
  "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018,GG_016,BC_015,MD_009,WS_007,MA_035,SB_006,FP_038,OS_028,MF_003;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008"
  "BAL-001|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:DP_004,BDT_005;REPRESENTATIVE-ILN-1:JF_001;MAYOR-ILN-CURRIMAO:EW_003;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
  "BAL-002|PRESIDENT:AJ_006;VICE-PRESIDENT:AH_006;SENATOR:JC_045,MA_035,JM_027,BC_015,RG_029,FP_038,AA_018,JD_001,KR_011,OS_028,EB_022,A_044;REPRESENTATIVE-PARTY-LIST:TENET_040"
  "BAL-002|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:DK_003;BOARD-MEMBER-ILN:HB_002,RW_001;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:BC_001;VICE-MAYOR-ILN-CURRIMAO:JL_002;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
  "BAL-003|PRESIDENT:DW_003;VICE-PRESIDENT:RDJ_005;SENATOR:JG_017,MF_003,TH_047,SH_030,MA_035,JR_008,CB_025,SB_006,EG_036,DC_019,RM_040,RM_024;REPRESENTATIVE-PARTY-LIST:LUCA_148"
  "BAL-003|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:NW_001;BOARD-MEMBER-ILN:RW_001,DJ_006;REPRESENTATIVE-ILN-1:RW_002;MAYOR-ILN-CURRIMAO:LJ_002;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
  "BAL-004|PRESIDENT:SJ_002;VICE-PRESIDENT:TH_001;SENATOR:CB_025,AA_018,SH_030,ATJ_041,RM_024,KR_011,AG_046,CE_023,ZS_014,BC_049,CB_005,PP_039;REPRESENTATIVE-PARTY-LIST:THE_MARTIAN_044"
  "BAL-004|GOVERNOR-ILN:RP_003;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:RW_003,DP_004;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:LJ_002;VICE-MAYOR-ILN-CURRIMAO:JF_003;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
  "BAL-005|PRESIDENT:AJ_006;VICE-PRESIDENT:RDJ_005;SENATOR:BC_015,A_044,MR_021,JL_004,HM_050,MF_003,LN_048,TS_026,AA_018,CB_005,ZS_014,TC_037;REPRESENTATIVE-PARTY-LIST:THE_GREEN_MILE_081"
  "BAL-005|GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:JH_004;BOARD-MEMBER-ILN:AD_007,DJ_006;REPRESENTATIVE-ILN-1:DC_004;MAYOR-ILN-CURRIMAO:BC_001;VICE-MAYOR-ILN-CURRIMAO:JL_002;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008"
)

for ballot in "${ballots[@]}"; do
  curl -X POST http://truth.test/api/election/cast-ballot \
    -H "Content-Type: application/json" \
    -d '{"raw": "'$ballot'"}'
  echo "Cast: $ballot"
done

# 5. Tally Votes
curl -X POST http://truth.test/api/election/tally-votes \
  -H "Content-Type: application/json" \
  -d '{"otp": "317537"}'

# 6. Attest Election Return
for attest in \
  "BEI:uuid-juan:signature123" \
  "BEI:uuid-maria:signature456" \
  "BEI:uuid-pedro:signature789"; do
  curl -X POST http://truth.test/api/election/attest-return \
    -H "Content-Type: application/json" \
    -d '{"attestation": "'$attest'"}'
  echo "Attested: $attest"
done

# 7. Record Statistics
curl -X PATCH http://truth.test/api/election/record-statistics \
  -H "Content-Type: application/json" \
  -d '{"watchers_count":5,"registered_voters_count":800,"actual_voters_count":700,"ballots_in_box_count":695,"unused_ballots_count":105}'

curl -X PATCH http://truth.test/api/election/record-statistics \
  -H "Content-Type: application/json" \
  -d '{"watchers_count":6,"registered_voters_count":801,"actual_voters_count":701,"ballots_in_box_count":696,"unused_ballots_count":106}'

# 8. Wrap Up Voting
curl -X POST http://truth.test/api/election/wrapup-voting
```
cdbf0f83ab4f7d46be767e73c59b5cbca9743dd5fb887142c96f4b2df38fa5ad *ubuntu-24.04.3-desktop-arm64.iso
dba22b48348731494e4ce3925c1b1f458c62375c3f12419fc5e144542f765756 *ubuntu-24.04.3-live-server-arm64+largemem.iso
2ee2163c9b901ff5926400e80759088ff3b879982a3956c02100495b489fd555 *ubuntu-24.04.3-live-server-arm64.iso
823d5db6c452d8cda75b9ad8253deeaba418a2a2f544d353136fe53d3c0be9d2 *ubuntu-24.04.3-live-server-ppc64el.iso
f469d225af0886d4221b9f147891976101b1faed379bad0070cc7c844a942a0f *ubuntu-24.04.3-live-server-riscv64.iso
968c208fad190780e8ec321c51bcf7d4b59bfaf34e393c6d8251243be45f549d *ubuntu-24.04.3-live-server-s390x.iso
04a87330d2dfbe29c29f69d2113d92bbde44daa516054074ff4b96c7ee3c528b *ubuntu-24.04.3-preinstalled-desktop-arm64+raspi.img.xz
9bb1799cee8965e6df0234c1c879dd35be1d87afe39b84951f278b6bd0433e56 *ubuntu-24.04.3-preinstalled-server-arm64+raspi.img.xz
4474947b7816128a934f7245c17ba0eff90ccb3e03b66cac221d7a3a1912c27b *ubuntu-24.04.3-preinstalled-server-riscv64+icicle.img.xz
36f57bcecb5201e30e78a1404758c6d8061f4561f8e598abec2a2c056e35ed72 *ubuntu-24.04.3-preinstalled-server-riscv64+jh7110.img.xz
b2d81bd6122b6868c102afd07ce62fc22293a05ff09366b170fae370a612b9fe *ubuntu-24.04.3-preinstalled-server-riscv64+licheerv.img.xz
890564e8659903a36a1f68f26c30ff76bd97cfc4e7db2484bec24f32c6432aab *ubuntu-24.04.3-preinstalled-server-riscv64+nezha.img.xz
202c17f9d39529aad5957f0fbcdb9083dc5ba49aa94706ec045e27616369694a *ubuntu-24.04.3-preinstalled-server-riscv64+pic64gx.img.xz
f385868c2870c4d16213bcfee50e9bd168400f34df1b4b6b3f6ceae19acd59de *ubuntu-24.04.3-preinstalled-server-riscv64+unmatched.img.xz
8e7553f229f5889e8698281404f05d565ae694066215d45df9178d9e865e23fe *ubuntu-24.04.3-preinstalled-server-riscv64.img.xz
edaf375ea0d1319da08e7154e9bc64cd4eead5af21ea032f2edaa335fadf0970 *ubuntu-24.04.3-wsl-arm64.wsl
