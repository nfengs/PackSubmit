.PHONY: tidy build run migrate seed test

tidy:
	go mod tidy

build:
	CGO_ENABLED=0 go build -trimpath -ldflags "-s -w" -o bin/packsubmit ./cmd/server

run:
	go run ./cmd/server -config configs/config.yaml

migrate:
	go run ./cmd/server -config configs/config.yaml -migrate

seed:
	go run ./cmd/server -config configs/config.yaml -migrate -seed

test:
	go test ./...
