-- Consultas de validação rápida (pós seed)
SET NAMES utf8mb4;

SELECT 'regiao_integracao' AS tabela, COUNT(*) AS qtd FROM regiao_integracao
UNION ALL
SELECT 'municipio', COUNT(*) FROM municipio
UNION ALL
SELECT 'partido', COUNT(*) FROM partido
UNION ALL
SELECT 'prefeito', COUNT(*) FROM prefeito
UNION ALL
SELECT 'mandato_prefeito', COUNT(*) FROM mandato_prefeito
UNION ALL
SELECT 'demografia_municipio', COUNT(*) FROM demografia_municipio;

-- Demografia sem município
SELECT d.*
FROM demografia_municipio d
LEFT JOIN municipio m ON m.id = d.municipio_id
WHERE m.id IS NULL;

-- Municípios sem demografia no ano 2026
SELECT m.id, m.nome
FROM municipio m
LEFT JOIN demografia_municipio d ON d.municipio_id = m.id AND d.ano_ref = 2026
WHERE d.id IS NULL
ORDER BY m.id;

-- Checagem de razão eleitores/populacao (suspeitos)
SELECT m.nome, d.populacao, d.eleitores, ROUND(d.eleitores / d.populacao, 3) AS razao
FROM demografia_municipio d
JOIN municipio m ON m.id = d.municipio_id
WHERE d.populacao > 0
ORDER BY razao DESC
LIMIT 30;
