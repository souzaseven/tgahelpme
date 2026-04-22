<?php
/* =========================================================
   Actions – Plantões
========================================================= */

function action_supports_list(PDO $pdo) {
  $onlyActive = ($_GET['only_active'] ?? '1') === '1';

  $sql = "
    SELECT id, nome, ativo, criado_em, atualizado_em
    FROM suportes_plantao
    " . ($onlyActive ? "WHERE ativo = 1" : "") . "
    ORDER BY nome ASC
  ";

  $st = $pdo->query($sql);
  jexit(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}

function action_summary(PDO $pdo) {
  $w = getPrevCurrentNextWeekends();

  jexit([
    'success'=>true,
    'weekends'=>[
      'prev'=>['range'=>$w['prev'],'plantao'=>getPlantaoBySaturday($pdo,$w['prev']['sabado'])],
      'current'=>['range'=>$w['current'],'plantao'=>getPlantaoBySaturday($pdo,$w['current']['sabado'])],
      'next'=>['range'=>$w['next'],'plantao'=>getPlantaoBySaturday($pdo,$w['next']['sabado'])],
    ]
  ]);
}

/* Repete o mesmo padrão para:
   - support_save
   - month
   - period
   - plantao_save
   - plantao_delete
*/
